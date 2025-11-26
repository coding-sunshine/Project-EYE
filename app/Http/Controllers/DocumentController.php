<?php

namespace App\Http\Controllers;

use App\Models\DocumentFile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Preview document (inline display in browser).
     *
     * @param int $id
     * @return Response
     */
    public function preview(int $id)
    {
        $document = DocumentFile::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            abort(404, 'Document not found');
        }

        $path = Storage::path($document->file_path);
        $mimeType = $document->mime_type ?? mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Download document (force download).
     *
     * @param int $id
     * @return Response
     */
    public function download(int $id)
    {
        $document = DocumentFile::findOrFail($id);

        if (!Storage::exists($document->file_path)) {
            abort(404, 'Document not found');
        }

        $path = Storage::path($document->file_path);
        $mimeType = $document->mime_type ?? mime_content_type($path);

        return response()->download($path, $document->original_filename, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Get document thumbnail (if available).
     *
     * @param int $id
     * @return Response
     */
    public function thumbnail(int $id)
    {
        $document = DocumentFile::findOrFail($id);

        if (!$document->thumbnail_path || !Storage::exists($document->thumbnail_path)) {
            abort(404, 'Thumbnail not found');
        }

        $path = Storage::path($document->thumbnail_path);
        $mimeType = mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
