<?php

namespace App\Features\Profile\Shared\Controllers;

use App\Core\BaseController;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileImageController extends BaseController
{
    /**
     * Serve a private profile image.
     */
    public function serve(string $filename): BinaryFileResponse
    {
        // Simple authentication check
        if (!Auth::check()) {
            abort(403);
        }

        $fullPath = storage_path("app/private/profile-image/{$filename}");

        if (!file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    }
}
