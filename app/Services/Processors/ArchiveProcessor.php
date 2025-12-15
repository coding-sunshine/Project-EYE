<?php

namespace App\Services\Processors;

use App\Models\ArchiveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Archive Processor
 *
 * Handles archive-specific processing:
 * - Metadata extraction (file count, uncompressed size, compression type)
 * - File list generation without full extraction
 * - Support for ZIP (native), RAR, TAR, 7Z, GZIP
 * - Graceful degradation when tools unavailable
 */
class ArchiveProcessor
{
    /**
     * Process an archive file and extract metadata.
     *
     * @param ArchiveFile $archive
     * @return ArchiveFile
     */
    public function process(ArchiveFile $archive): ArchiveFile
    {
        try {
            $fullPath = Storage::path($archive->file_path);

            // Determine compression type from MIME type
            $compressionType = $this->detectCompressionType($archive->mime_type);

            // Extract archive metadata
            $metadata = $this->extractMetadata($fullPath, $compressionType);

            // Update archive record with metadata
            $archive->update([
                'compression_type' => $compressionType,
                'file_count' => $metadata['file_count'] ?? null,
                'uncompressed_size' => $metadata['uncompressed_size'] ?? null,
                'file_list' => $metadata['file_list'] ?? null,
                'processing_status' => 'completed',
            ]);

            Log::info('Archive processed successfully', [
                'archive_id' => $archive->id,
                'compression_type' => $compressionType,
                'file_count' => $metadata['file_count'] ?? 0,
            ]);

            return $archive;

        } catch (\Exception $e) {
            Log::error('Archive processing failed', [
                'archive_id' => $archive->id,
                'error' => $e->getMessage()
            ]);

            $archive->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Detect compression type from MIME type.
     *
     * @param string $mimeType
     * @return string
     */
    protected function detectCompressionType(string $mimeType): string
    {
        return match($mimeType) {
            'application/zip' => 'zip',
            'application/x-rar-compressed', 'application/vnd.rar' => 'rar',
            'application/x-tar' => 'tar',
            'application/x-7z-compressed' => '7z',
            'application/gzip', 'application/x-gzip' => 'gzip',
            default => 'unknown',
        };
    }

    /**
     * Extract metadata from archive file.
     *
     * @param string $filePath
     * @param string $compressionType
     * @return array
     */
    protected function extractMetadata(string $filePath, string $compressionType): array
    {
        return match($compressionType) {
            'zip' => $this->extractZipMetadata($filePath),
            'rar' => $this->extractRarMetadata($filePath),
            'tar' => $this->extractTarMetadata($filePath),
            'gzip' => $this->extractGzipMetadata($filePath),
            '7z' => $this->extract7zMetadata($filePath),
            default => [],
        };
    }

    /**
     * Extract metadata from ZIP archive using native PHP ZipArchive.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractZipMetadata(string $filePath): array
    {
        try {
            $zip = new ZipArchive();

            if ($zip->open($filePath) !== true) {
                throw new \Exception('Failed to open ZIP archive');
            }

            $fileCount = $zip->numFiles;
            $uncompressedSize = 0;
            $fileList = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $uncompressedSize += $stat['size'];

                // Store file information (limit to first 1000 files)
                if ($i < 1000) {
                    $fileList[] = [
                        'name' => $stat['name'],
                        'size' => $stat['size'],
                        'compressed_size' => $stat['comp_size'],
                        'is_directory' => str_ends_with($stat['name'], '/'),
                    ];
                }
            }

            $zip->close();

            return [
                'file_count' => $fileCount,
                'uncompressed_size' => $uncompressedSize,
                'file_list' => $fileList,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to extract ZIP metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract metadata from RAR archive using unrar command.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractRarMetadata(string $filePath): array
    {
        if (!$this->isUnrarAvailable()) {
            Log::warning('unrar not available, skipping RAR metadata extraction');
            return [];
        }

        try {
            // List archive contents
            $command = sprintf('unrar l -v %s 2>&1', escapeshellarg($filePath));
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to list RAR archive');
            }

            // Parse output for file count and size
            $fileCount = 0;
            $uncompressedSize = 0;
            $fileList = [];
            $inFileList = false;

            foreach ($output as $line) {
                // Start of file list
                if (strpos($line, '-------') !== false) {
                    $inFileList = !$inFileList;
                    continue;
                }

                if ($inFileList && trim($line)) {
                    // Parse file line (size, date, time, name)
                    if (preg_match('/\s+(\d+)\s+\d{2}-\d{2}-\d{2}\s+\d{2}:\d{2}\s+(.+)$/', $line, $matches)) {
                        $fileCount++;
                        $size = (int)$matches[1];
                        $uncompressedSize += $size;

                        if ($fileCount <= 1000) {
                            $fileList[] = [
                                'name' => trim($matches[2]),
                                'size' => $size,
                                'is_directory' => false,
                            ];
                        }
                    }
                }
            }

            return [
                'file_count' => $fileCount,
                'uncompressed_size' => $uncompressedSize,
                'file_list' => $fileList,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to extract RAR metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract metadata from TAR archive using tar command.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractTarMetadata(string $filePath): array
    {
        if (!$this->isTarAvailable()) {
            Log::warning('tar not available, skipping TAR metadata extraction');
            return [];
        }

        try {
            // List archive contents with sizes
            $command = sprintf('tar -tvf %s 2>&1', escapeshellarg($filePath));
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to list TAR archive');
            }

            $fileCount = 0;
            $uncompressedSize = 0;
            $fileList = [];

            foreach ($output as $line) {
                // Parse tar listing format
                if (preg_match('/^([drwx-]+)\s+\S+\s+\S+\s+(\d+)\s+.+\s(.+)$/', $line, $matches)) {
                    $fileCount++;
                    $size = (int)$matches[2];
                    $uncompressedSize += $size;

                    if ($fileCount <= 1000) {
                        $fileList[] = [
                            'name' => trim($matches[3]),
                            'size' => $size,
                            'is_directory' => $matches[1][0] === 'd',
                        ];
                    }
                }
            }

            return [
                'file_count' => $fileCount,
                'uncompressed_size' => $uncompressedSize,
                'file_list' => $fileList,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to extract TAR metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract metadata from GZIP archive.
     *
     * @param string $filePath
     * @return array
     */
    protected function extractGzipMetadata(string $filePath): array
    {
        try {
            // GZIP typically contains a single file
            $filename = basename($filePath, '.gz');

            return [
                'file_count' => 1,
                'uncompressed_size' => null, // Cannot easily determine without decompression
                'file_list' => [
                    [
                        'name' => $filename,
                        'size' => null,
                        'is_directory' => false,
                    ]
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to extract GZIP metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Extract metadata from 7Z archive using 7z command.
     *
     * @param string $filePath
     * @return array
     */
    protected function extract7zMetadata(string $filePath): array
    {
        if (!$this->is7zAvailable()) {
            Log::warning('7z not available, skipping 7Z metadata extraction');
            return [];
        }

        try {
            // List archive contents
            $command = sprintf('7z l %s 2>&1', escapeshellarg($filePath));
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception('Failed to list 7Z archive');
            }

            $fileCount = 0;
            $uncompressedSize = 0;
            $fileList = [];
            $inFileList = false;

            foreach ($output as $line) {
                // Start/end of file list
                if (strpos($line, '-----') !== false) {
                    $inFileList = !$inFileList;
                    continue;
                }

                if ($inFileList && trim($line)) {
                    // Parse 7z listing format
                    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+([D\.]).....\s+(\d+)\s+\d+\s+(.+)$/', $line, $matches)) {
                        $fileCount++;
                        $size = (int)$matches[2];
                        $uncompressedSize += $size;

                        if ($fileCount <= 1000) {
                            $fileList[] = [
                                'name' => trim($matches[3]),
                                'size' => $size,
                                'is_directory' => $matches[1] === 'D',
                            ];
                        }
                    }
                }
            }

            return [
                'file_count' => $fileCount,
                'uncompressed_size' => $uncompressedSize,
                'file_list' => $fileList,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to extract 7Z metadata', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if unrar is available on the system.
     *
     * @return bool
     */
    public function isUnrarAvailable(): bool
    {
        exec('which unrar', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if tar is available on the system.
     *
     * @return bool
     */
    public function isTarAvailable(): bool
    {
        exec('which tar', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if 7z is available on the system.
     *
     * @return bool
     */
    public function is7zAvailable(): bool
    {
        exec('which 7z', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Get archive information summary.
     *
     * @param ArchiveFile $archive
     * @return array
     */
    public function getArchiveInfo(ArchiveFile $archive): array
    {
        return [
            'type' => $archive->archive_type,
            'compression' => $archive->compression_ratio ? $archive->compression_ratio . '%' : 'N/A',
            'files' => $archive->file_count ?? 0,
            'compressed_size' => $archive->file_size_formatted,
            'uncompressed_size' => $archive->uncompressed_size_formatted ?? 'N/A',
            'has_password' => $archive->hasPassword(),
        ];
    }
}
