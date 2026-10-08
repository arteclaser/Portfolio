<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\MediaStore;
use App\Services\MediaVisibility;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class MediaController extends Controller
{
    public function __construct(private MediaStore $store) {}

    /** Somente arquivos usados por conteúdo publicado. Os demais respondem 404. */
    public function public(Request $request, Media $media, string $variant, MediaVisibility $visibility)
    {
        if (! $visibility->isPublic($media)) {
            abort(404);
        }

        return $this->send($request, $media, $variant, true);
    }

    public function panel(Request $request, Media $media, string $variant)
    {
        $this->authorize('view', $media);

        return $this->send($request, $media, $variant, false);
    }

    private function send(Request $request, Media $media, string $variant, bool $public): BinaryFileResponse
    {
        if ($media->isDocument() !== ($variant === 'file')) {
            abort(404);
        }
        $path = $this->store->absolutePath($media, $variant) ?? abort(404);
        $mime = $media->isDocument() ? 'application/pdf' : (str_ends_with($path, '.webp') ? 'image/webp' : 'image/jpeg');

        $response = new BinaryFileResponse($path, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ], $public, null, true, true);

        if ($media->isDocument()) {
            $name = preg_replace('/[^\w.\- ]+/u', '', (string) ($media->original_name ?: 'documento.pdf')) ?: 'documento.pdf';
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name, 'documento.pdf');
            $response->headers->set('Content-Security-Policy', 'sandbox');
        }
        if ($public) {
            $response->setPublic();
            $response->setMaxAge(3600);
        } else {
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-store');
        }
        $response->setEtag(sha1($media->uuid.'|'.$variant.'|'.$media->updated_at));
        $response->isNotModified($request);

        return $response;
    }
}
