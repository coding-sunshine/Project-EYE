<?php

namespace App\Models;

class DocumentFile extends MediaFile
{
    /**
     * Get the media type for documents.
     *
     * @return string
     */
    protected function getMediaType(): string
    {
        return 'document';
    }

    /**
     * Get the document preview URL.
     *
     * @return string
     */
    public function getPreviewUrlAttribute(): string
    {
        return route('documents.preview', $this->id);
    }

    /**
     * Get the download URL.
     *
     * @return string
     */
    public function getDownloadUrlAttribute(): string
    {
        return route('documents.download', $this->id);
    }

    /**
     * Get the thumbnail URL for the document (first page).
     *
     * @return string
     */
    public function getThumbnailUrlAttribute(): string
    {
        // Document thumbnails are generated from first page
        $filename = pathinfo($this->file_path, PATHINFO_FILENAME) . '.jpg';
        return asset('storage/thumbnails/' . $filename);
    }

    /**
     * Get document type based on mime type.
     *
     * @return string
     */
    public function getDocumentTypeAttribute(): string
    {
        return match($this->mime_type) {
            'application/pdf' => 'PDF',
            'application/msword' => 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'application/vnd.ms-excel' => 'Excel Spreadsheet',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Spreadsheet',
            'application/vnd.ms-powerpoint' => 'PowerPoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
            'text/plain' => 'Text File',
            'text/csv' => 'CSV File',
            default => 'Document',
        };
    }

    /**
     * Get document icon based on type.
     *
     * @return string
     */
    public function getDocumentIconAttribute(): string
    {
        return match($this->mime_type) {
            'application/pdf' => 'fa-file-pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fa-file-word',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fa-file-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fa-file-powerpoint',
            'text/plain' => 'fa-file-alt',
            'text/csv' => 'fa-file-csv',
            default => 'fa-file',
        };
    }

    /**
     * Search within the document's extracted text.
     *
     * @param string $query
     * @return array
     */
    public function searchInText(string $query): array
    {
        if (!$this->extracted_text) {
            return [];
        }

        $text = $this->extracted_text;
        $query = strtolower($query);
        $textLower = strtolower($text);

        $matches = [];
        $offset = 0;

        while (($pos = strpos($textLower, $query, $offset)) !== false) {
            // Extract context around the match
            $contextStart = max(0, $pos - 50);
            $contextEnd = min(strlen($text), $pos + strlen($query) + 50);
            $context = substr($text, $contextStart, $contextEnd - $contextStart);

            $matches[] = [
                'position' => $pos,
                'context' => '...' . $context . '...',
            ];

            $offset = $pos + 1;

            // Limit to 10 matches
            if (count($matches) >= 10) {
                break;
            }
        }

        return $matches;
    }

    /**
     * Get text preview (first 500 characters).
     *
     * @return string|null
     */
    public function getTextPreviewAttribute(): ?string
    {
        if (!$this->extracted_text) {
            return null;
        }

        $preview = substr($this->extracted_text, 0, 500);

        if (strlen($this->extracted_text) > 500) {
            $preview .= '...';
        }

        return $preview;
    }

    /**
     * Get word count from extracted text.
     *
     * @return int
     */
    public function getWordCountAttribute(): int
    {
        if (!$this->extracted_text) {
            return 0;
        }

        return str_word_count($this->extracted_text);
    }
}
