<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class FileUploadMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Log detailed request information for evidence upload
        if ($request->hasFile('evidence')) {
            $file = $request->file('evidence');

            // Validate file based on configuration
            $validationResult = $this->validateFile($file);

            if (!$validationResult['valid']) {
                Log::error('File Upload Validation Failed', [
                    'errors' => $validationResult['errors'],
                    'file_details' => $this->getFileDetails($file),
                    'request_data' => [
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'content_type' => $request->header('Content-Type')
                    ]
                ]);

                return response()->json([
                    'message' => 'File upload validation failed',
                    'errors' => $validationResult['errors'],
                    'debug' => $this->getFileDetails($file)
                ], 422);
            }
        }

        return $next($request);
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file)
    {
        $errors = [];
        $allowedMimeTypes = Config::get('filesystems.allowed_mime_types.evidence', []);
        $maxUploadSize = Config::get('filesystems.max_upload_size.evidence', 10 * 1024 * 1024);

        // Check if file is valid
        if (!$file->isValid()) {
            $errors[] = 'Invalid file upload: ' . $file->getErrorMessage();
        }

        // Check file size
        if ($file->getSize() > $maxUploadSize) {
            $errors[] = "File size exceeds maximum limit of " . ($maxUploadSize / 1024 / 1024) . "MB";
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Unsupported file type. Allowed types: " . implode(', ', $allowedMimeTypes);
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors
        ];
    }

    /**
     * Get detailed file information for logging
     */
    private function getFileDetails($file)
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'original_extension' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_valid' => $file->isValid(),
            'error' => $file->getError(),
            'error_message' => $file->getErrorMessage(),
            'path' => $file->path()
        ];
    }
}
