<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Config;

class FileUploadServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Custom validation rules for file uploads
        Validator::extend('evidence_file', function ($attribute, $value, $parameters, $validator) {
            $allowedMimeTypes = Config::get('filesystems.allowed_mime_types.evidence', []);
            $maxUploadSize = Config::get('filesystems.max_upload_size.evidence', 10 * 1024 * 1024);

            // Detailed validation checks
            $errors = [];

            // Check file validity
            if (!$value->isValid()) {
                $errors[] = 'Invalid file upload: ' . $value->getErrorMessage();
                return false;
            }

            // Check file size
            if ($value->getSize() > $maxUploadSize) {
                $errors[] = "File size exceeds maximum limit of " . ($maxUploadSize / 1024 / 1024) . "MB";
                return false;
            }

            // Check MIME type
            $mimeType = $value->getMimeType();
            if (!in_array($mimeType, $allowedMimeTypes)) {
                $errors[] = "Unsupported file type. Allowed types: " . implode(', ', $allowedMimeTypes);
                return false;
            }

            return true;
        }, 'Invalid evidence file. Check file type and size.');

        // Custom error messages with more context
        Validator::replacer('evidence_file', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, 'Invalid evidence file. Please upload a valid image file (JPEG, PNG, GIF, WEBP, BMP, TIFF) not exceeding 10MB.');
        });
    }
}
