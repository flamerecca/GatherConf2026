<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Mime\MimeTypes;

class AssetController extends Controller
{
    /**
     * Serve a fingerprinted Vite build file with a one year browser cache.
     *
     * The web server sends static files without a Cache-Control header, so the files are served
     * here instead. The file name carries a content hash, which makes a long immutable lifetime safe.
     */
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $path = public_path('build/assets/'.basename($file));

        abort_unless(is_file($path), 404);

        $response = response()->file($path, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => $this->contentType($path),
        ]);

        $response->setAutoEtag();
        $response->setAutoLastModified();
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Pick the type from the extension, because sniffing the content reports scripts as text/plain.
     */
    private function contentType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'js', 'mjs' => 'text/javascript; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'json' => 'application/json',
            'map' => 'application/json',
            default => MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream',
        };
    }
}
