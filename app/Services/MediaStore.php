<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Recebe imagens e documentos, valida tipo, tamanho e conteúdo, e gera versões
 * otimizadas. As imagens são reprocessadas (o que remove metadados EXIF, como a
 * localização GPS) e reduzidas sem ampliar nem deformar. Tudo fica em disco
 * privado; o acesso público passa pelo controlador de mídia.
 */
class MediaStore
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Limite de pixels decodificados para proteger a memória do servidor. */
    public const MAX_PIXELS = 40_000_000;

    public const DISK = 'local';

    public function storeUploadedImage(UploadedFile $file, Portfolio $portfolio, ?User $user): Media
    {
        $this->assertUploadOk($file, $portfolio);

        return $this->storeImageFromPath($file->getRealPath(), $portfolio, $user, $file->getClientOriginalName());
    }

    public function storeImageFromPath(string $path, Portfolio $portfolio, ?User $user, ?string $originalName = null, ?string $sourceUrl = null): Media
    {
        $size = @filesize($path) ?: 0;
        if ($size > $portfolio->maxUploadBytes()) {
            throw new MediaException($this->tooLargeMessage($portfolio));
        }
        $mime = $this->detectMime($path);
        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw new MediaException('Formato não aceito. Envie imagens JPEG, PNG ou WebP.');
        }
        $info = @getimagesize($path);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            throw new MediaException('O arquivo não é uma imagem válida ou está corrompido.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS || $info[0] > 12000 || $info[1] > 12000) {
            throw new MediaException('A imagem tem dimensões grandes demais (máximo de 40 megapixels).');
        }

        @ini_set('memory_limit', '512M');
        $uuid = (string) Str::uuid();
        $directory = 'media/'.$uuid;
        $disk = Storage::disk(self::DISK);
        $format = function_exists('imagewebp') ? 'webp' : 'jpg';
        $variants = [];

        try {
            $manager = ImageManager::gd(autoOrientation: true, decodeAnimation: false);
            $image = $manager->read($path);
            // Da maior para a menor: cada variante parte da anterior, sem ampliar.
            foreach (array_reverse(Media::VARIANTS, true) as $name => $maxWidth) {
                $image->scaleDown(width: $maxWidth, height: (int) round($maxWidth * 1.5));
                $encoded = $format === 'webp' ? $image->toWebp(quality: 80) : $image->toJpeg(quality: 82, progressive: true);
                $relative = "{$directory}/{$name}.{$format}";
                $disk->put($relative, (string) $encoded);
                $variants[$name] = ['path' => $relative, 'w' => $image->width(), 'h' => $image->height()];
            }
        } catch (Throwable $e) {
            $disk->deleteDirectory($directory);
            report($e);

            throw new MediaException('Não foi possível processar a imagem. Verifique se o arquivo não está corrompido.');
        }

        return Media::create([
            'uuid' => $uuid,
            'portfolio_id' => $portfolio->id,
            'uploaded_by' => $user?->id,
            'kind' => 'image',
            'disk' => self::DISK,
            'directory' => $directory,
            'mime' => $format === 'webp' ? 'image/webp' : 'image/jpeg',
            'size' => $size,
            'width' => $variants['xl']['w'],
            'height' => $variants['xl']['h'],
            'original_name' => $originalName ? Str::limit(basename($originalName), 200, '') : null,
            'variants' => $variants,
            'source_url' => $sourceUrl,
        ]);
    }

    public function storeUploadedDocument(UploadedFile $file, Portfolio $portfolio, ?User $user): Media
    {
        $this->assertUploadOk($file, $portfolio, 4);
        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');
        $head = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }
        if ($head !== '%PDF-' || $this->detectMime($path) !== 'application/pdf') {
            throw new MediaException('Somente documentos PDF são aceitos.');
        }
        $uuid = (string) Str::uuid();
        $directory = 'media/'.$uuid;
        $relative = $directory.'/documento.pdf';
        Storage::disk(self::DISK)->putFileAs($directory, $file, 'documento.pdf');

        return Media::create([
            'uuid' => $uuid,
            'portfolio_id' => $portfolio->id,
            'uploaded_by' => $user?->id,
            'kind' => 'document',
            'disk' => self::DISK,
            'directory' => $directory,
            'mime' => 'application/pdf',
            'size' => $file->getSize(),
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 200, ''),
            'variants' => ['file' => ['path' => $relative]],
        ]);
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->deleteDirectory($media->directory);
        $media->delete();
    }

    public function absolutePath(Media $media, string $variant): ?string
    {
        $relative = $media->variantPath($variant);
        if (! $relative) {
            return null;
        }
        $disk = Storage::disk($media->disk);

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    private function assertUploadOk(UploadedFile $file, Portfolio $portfolio, int $multiplier = 1): void
    {
        if (! $file->isValid()) {
            $error = $file->getError();
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new MediaException($this->tooLargeMessage($portfolio));
            }

            throw new MediaException('O envio do arquivo falhou. Tente novamente.');
        }
        if ($file->getSize() > $portfolio->maxUploadBytes() * $multiplier) {
            throw new MediaException($this->tooLargeMessage($portfolio, $multiplier));
        }
    }

    private function tooLargeMessage(Portfolio $portfolio, int $multiplier = 1): string
    {
        $mb = rtrim(rtrim(number_format($portfolio->maxUploadBytes() * $multiplier / 1048576, 1, ',', ''), '0'), ',');

        return "O arquivo ultrapassa o limite de {$mb} MB.";
    }

    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $path) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        return $mime;
    }
}
