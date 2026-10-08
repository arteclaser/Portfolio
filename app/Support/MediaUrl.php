<?php

namespace App\Support;

use App\Models\Media;

/**
 * Gera endereços de mídia conforme o contexto. Nas páginas públicas, usa a rota
 * pública (que só entrega arquivos de conteúdo publicado). No painel e nas prévias,
 * usa a rota autenticada.
 */
class MediaUrl
{
    private static bool $panel = false;

    public static function usePanel(bool $panel = true): void
    {
        self::$panel = $panel;
    }

    public static function inPanel(): bool
    {
        return self::$panel;
    }

    public static function url(?Media $media, string $variant = 'md', bool $absolute = false): ?string
    {
        if (! $media) {
            return null;
        }
        $variant = $media->isDocument() ? 'file' : $variant;
        $name = self::$panel ? 'panel.media.file' : 'media.show';

        return route($name, ['media' => $media->uuid, 'variant' => $variant], $absolute);
    }

    /** Endereço público absoluto (para metadados de compartilhamento). */
    public static function publicAbsolute(?Media $media, string $variant = 'lg'): ?string
    {
        return $media ? route('media.show', ['media' => $media->uuid, 'variant' => $variant], true) : null;
    }

    public static function srcset(?Media $media): string
    {
        if (! $media || ! $media->isImage()) {
            return '';
        }
        $parts = [];
        $seen = [];
        foreach ($media->variantWidths() as $name => $width) {
            if (isset($seen[$width])) {
                continue;
            }
            $seen[$width] = true;
            $parts[] = self::url($media, $name).' '.$width.'w';
        }

        return implode(', ', $parts);
    }
}
