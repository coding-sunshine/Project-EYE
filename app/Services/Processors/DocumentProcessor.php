<?php

namespace App\Services\Processors;

use App\Models\DocumentFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Document Processor
 *
 * Handles document-specific processing:
 * - Text extraction from PDFs
 * - Office document parsing (Word, Excel, PowerPoint)
 * - Plain text reading
 * - Thumbnail generation (first page preview)
 * - Metadata extraction
 */
class DocumentProcessor
{
    /**
     * Process a document file and extract content.
     *
     * @param DocumentFile $document
     * @return DocumentFile
     */
    public function process(DocumentFile $document): DocumentFile
    {
        try {
            $fullPath = Storage::path($document->file_path);

            // Extract text content
            $extractedText = $this->extractText($fullPath, $document->mime_type);

            // Extract metadata
            $metadata = $this->extractMetadata($fullPath, $document->mime_type);

            // Update document record
            $document->update([
                'extracted_text' => $extractedText,
                'page_count' => $metadata['page_count'] ?? null,
                'processing_status' => 'completed',
            ]);

            // Generate thumbnail for PDFs
            if ($document->mime_type === 'application/pdf') {
                $this->generateThumbnail($document);
            }

            Log::info('Document processed successfully', [
                'document_id' => $document->id,
                'mime_type' => $document->mime_type,
                'text_length' => strlen($extractedText),
                'page_count' => $metadata['page_count'] ?? null,
            ]);

            return $document;

        } catch (\Exception $e) {
            Log::error('Document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);

            $document->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract text content from document.
     *
     * @param string $filePath
     * @param string $mimeType
     * @return string
     */
    protected function extractText(string $filePath, string $mimeType): string
    {
        return match($mimeType) {
            'application/pdf' => $this->extractTextFromPdf($filePath),
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => $this->extractTextFromWord($filePath),
            'text/plain' => $this->extractTextFromPlainText($filePath),
            'text/csv' => $this->extractTextFromCsv($filePath),
            'text/markdown', 'text/x-markdown' => $this->extractTextFromMarkdown($filePath),
            'application/json' => $this->extractTextFromJson($filePath),
            'application/xml', 'text/xml' => $this->extractTextFromXml($filePath),
            'application/rtf' => $this->extractTextFromRtf($filePath),
            'application/vnd.oasis.opendocument.text' => $this->extractTextFromOdt($filePath),
            'application/vnd.oasis.opendocument.spreadsheet' => $this->extractTextFromOds($filePath),
            'application/vnd.oasis.opendocument.presentation' => $this->extractTextFromOdp($filePath),
            'application/epub+zip' => $this->extractTextFromEpub($filePath),
            default => '',
        };
    }

    /**
     * Extract text from PDF using pdftotext or PHP library.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromPdf(string $filePath): string
    {
        // Try pdftotext command line tool first
        if ($this->isPdftotextAvailable()) {
            try {
                $command = sprintf('pdftotext %s - 2>&1', escapeshellarg($filePath));
                exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    return implode("\n", $output);
                }
            } catch (\Exception $e) {
                Log::warning('pdftotext extraction failed', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: Return empty string if no tools available
        // In production, you might want to use libraries like smalot/pdfparser
        Log::warning('No PDF text extraction tool available');
        return '';
    }

    /**
     * Extract text from Word document.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromWord(string $filePath): string
    {
        // For .docx files, we can extract text from XML
        if (str_ends_with($filePath, '.docx')) {
            try {
                $zip = new \ZipArchive();
                if ($zip->open($filePath) === true) {
                    $content = $zip->getFromName('word/document.xml');
                    $zip->close();

                    if ($content) {
                        // Strip XML tags and decode entities
                        $text = strip_tags($content);
                        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Word text extraction failed', ['error' => $e->getMessage()]);
            }
        }

        // For .doc files or if .docx extraction fails, try antiword
        if ($this->isAntiwordAvailable()) {
            try {
                $command = sprintf('antiword %s 2>&1', escapeshellarg($filePath));
                exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    return implode("\n", $output);
                }
            } catch (\Exception $e) {
                Log::warning('antiword extraction failed', ['error' => $e->getMessage()]);
            }
        }

        return '';
    }

    /**
     * Extract text from plain text file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromPlainText(string $filePath): string
    {
        try {
            return file_get_contents($filePath);
        } catch (\Exception $e) {
            Log::error('Failed to read plain text file', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from CSV file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromCsv(string $filePath): string
    {
        try {
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                return '';
            }

            $text = [];
            while (($data = fgetcsv($handle)) !== false) {
                $text[] = implode(' | ', $data);
            }
            fclose($handle);

            return implode("\n", $text);
        } catch (\Exception $e) {
            Log::error('Failed to read CSV file', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from Markdown file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromMarkdown(string $filePath): string
    {
        // Markdown is plain text with formatting syntax
        return $this->extractTextFromPlainText($filePath);
    }

    /**
     * Extract text from JSON file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromJson(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);
            $json = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                // Pretty print JSON for better readability
                return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // If invalid JSON, return as-is
            return $content;
        } catch (\Exception $e) {
            Log::error('Failed to read JSON file', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from XML file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromXml(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);

            // Try to parse and pretty print XML
            $xml = simplexml_load_string($content);
            if ($xml !== false) {
                $dom = new \DOMDocument('1.0');
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($xml->asXML());
                return $dom->saveXML();
            }

            // If parsing fails, return as-is
            return $content;
        } catch (\Exception $e) {
            Log::error('Failed to read XML file', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from RTF file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromRtf(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);

            // Basic RTF text extraction - strip RTF control codes
            // Remove RTF header and control words
            $text = preg_replace('/\\\\[a-z]{1,32}(-?[0-9]{1,10})?[ ]?|\\\\\n|\\\\\r|\\\\\'[0-9a-f]{2}/i', '', $content);

            // Remove remaining braces and extra whitespace
            $text = preg_replace('/[{}]/', '', $text);
            $text = trim(preg_replace('/\s+/', ' ', $text));

            return $text;
        } catch (\Exception $e) {
            Log::error('Failed to read RTF file', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Extract text from ODT (OpenDocument Text).
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromOdt(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $content = $zip->getFromName('content.xml');
                $zip->close();

                if ($content) {
                    // Strip XML tags and decode entities
                    $text = strip_tags($content);
                    return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } catch (\Exception $e) {
            Log::warning('ODT text extraction failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Extract text from ODS (OpenDocument Spreadsheet).
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromOds(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $content = $zip->getFromName('content.xml');
                $zip->close();

                if ($content) {
                    // Parse XML and extract table data
                    $xml = simplexml_load_string($content);
                    if ($xml !== false) {
                        $text = [];

                        // Register namespaces
                        $xml->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
                        $xml->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

                        // Extract cells from all sheets
                        $cells = $xml->xpath('//table:table-cell');
                        foreach ($cells as $cell) {
                            $cellText = trim((string) $cell->children('urn:oasis:names:tc:opendocument:xmlns:text:1.0')->p);
                            if ($cellText) {
                                $text[] = $cellText;
                            }
                        }

                        return implode(' | ', $text);
                    }

                    // Fallback: strip tags
                    return strip_tags($content);
                }
            }
        } catch (\Exception $e) {
            Log::warning('ODS text extraction failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Extract text from ODP (OpenDocument Presentation).
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromOdp(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $content = $zip->getFromName('content.xml');
                $zip->close();

                if ($content) {
                    // Strip XML tags and decode entities
                    $text = strip_tags($content);
                    return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        } catch (\Exception $e) {
            Log::warning('ODP text extraction failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Extract text from EPUB file.
     *
     * @param string $filePath
     * @return string
     */
    protected function extractTextFromEpub(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) === true) {
                $text = [];

                // EPUB files contain XHTML content files
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);

                    // Look for content files (typically in OEBPS folder with .html/.xhtml extension)
                    if (preg_match('/\.(x?html?)$/i', $filename)) {
                        $content = $zip->getFromIndex($i);
                        if ($content) {
                            // Strip HTML tags and decode entities
                            $cleanText = strip_tags($content);
                            $cleanText = html_entity_decode($cleanText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));

                            if ($cleanText) {
                                $text[] = $cleanText;
                            }
                        }
                    }
                }

                $zip->close();
                return implode("\n\n", $text);
            }
        } catch (\Exception $e) {
            Log::warning('EPUB text extraction failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Extract metadata from document.
     *
     * @param string $filePath
     * @param string $mimeType
     * @return array
     */
    protected function extractMetadata(string $filePath, string $mimeType): array
    {
        $metadata = [];

        if ($mimeType === 'application/pdf') {
            $metadata['page_count'] = $this->getPdfPageCount($filePath);
        }

        return $metadata;
    }

    /**
     * Get PDF page count.
     *
     * @param string $filePath
     * @return int|null
     */
    protected function getPdfPageCount(string $filePath): ?int
    {
        try {
            if ($this->isPdfinfoAvailable()) {
                $command = sprintf('pdfinfo %s 2>&1', escapeshellarg($filePath));
                exec($command, $output, $returnCode);

                if ($returnCode === 0) {
                    foreach ($output as $line) {
                        if (preg_match('/^Pages:\s+(\d+)/', $line, $matches)) {
                            return (int) $matches[1];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get PDF page count', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Generate thumbnail from PDF first page.
     *
     * @param DocumentFile $document
     * @return bool
     */
    public function generateThumbnail(DocumentFile $document): bool
    {
        // Requires ImageMagick (convert command) or Ghostscript
        if (!$this->isImageMagickAvailable()) {
            Log::warning('ImageMagick not available, skipping PDF thumbnail generation');
            return false;
        }

        try {
            $sourcePath = Storage::path($document->file_path);
            $filename = pathinfo($document->file_path, PATHINFO_FILENAME) . '.jpg';
            $thumbnailPath = 'public/thumbnails/' . $filename;
            $fullThumbnailPath = Storage::path($thumbnailPath);

            // Ensure thumbnails directory exists
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            // Convert first page to thumbnail
            $command = sprintf(
                'convert -density 150 %s[0] -quality 90 -resize 800x %s 2>&1',
                escapeshellarg($sourcePath),
                escapeshellarg($fullThumbnailPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($fullThumbnailPath)) {
                $document->update(['thumbnail_path' => $thumbnailPath]);
                Log::info('Document thumbnail generated', [
                    'document_id' => $document->id,
                    'thumbnail_path' => $thumbnailPath,
                ]);
                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to generate document thumbnail', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if pdftotext is available.
     *
     * @return bool
     */
    protected function isPdftotextAvailable(): bool
    {
        exec('which pdftotext', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if pdfinfo is available.
     *
     * @return bool
     */
    protected function isPdfinfoAvailable(): bool
    {
        exec('which pdfinfo', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if antiword is available.
     *
     * @return bool
     */
    protected function isAntiwordAvailable(): bool
    {
        exec('which antiword', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Check if ImageMagick is available.
     *
     * @return bool
     */
    protected function isImageMagickAvailable(): bool
    {
        exec('which convert', $output, $returnCode);
        return $returnCode === 0;
    }
}
