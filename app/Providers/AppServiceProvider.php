<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Simple container bindings for OCR and Face services
        $this->app->singleton('app.services.ocr', function () {
            return new class {
                public function extract(string $storagePath): array
                {
                    // Placeholder OCR: return minimal structure
                    return [
                        'raw_text' => null,
                        'fields' => [
                            'nik' => null,
                            'nama' => null,
                            'alamat' => null,
                        ],
                    ];
                }
            };
        });

        $this->app->singleton('app.services.face', function () {
            return new class {
                public function verify(string $storagePath, string $expectedName): bool
                {
                    // Placeholder Face Verification: always true for now
                    return true;
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
