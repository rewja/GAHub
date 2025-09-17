<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LargeFileUploadMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Log file upload details for debugging
            if ($request->hasFile('evidence')) {
                $file = $request->file('evidence');

                Log::emergency('Large File Upload Middleware', [
                    'file_details' => [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'max_size' => 10 * 1024 * 1024, // 10MB
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage()
                    ],
                    'request_details' => [
                        'method' => $request->method(),
                        'url' => $request->fullUrl(),
                        'content_type' => $request->header('Content-Type')
                    ]
                ]);

                // Additional validation
                if (!$file->isValid()) {
                    return response()->json([
                        'message' => 'File upload failed',
                        'error' => $file->getErrorMessage()
                    ], 422);
                }
            }

            // Increase PHP configuration limits for file upload
            ini_set('upload_max_filesize', '10M');
            ini_set('post_max_size', '10M');
            ini_set('max_execution_time', 300);
            ini_set('max_input_time', 300);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Large File Upload Middleware Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Internal server error during file upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
