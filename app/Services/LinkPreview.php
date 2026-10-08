<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Identifica o provedor de um link e tenta obter metadados públicos (Open Graph,
 * oEmbed do YouTube). Nunca executa nem armazena HTML recebido; apenas textos
 * curtos e o endereço de uma imagem sugerida, que só é baixada se a equipe escolher.
 */
class LinkPreview
{
    public function __construct(private SafeFetcher $fetcher) {}

    /** @return array{provider: string, kind: string, embed_id: ?string} */
    public function detect(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com', 'music.youtube.com'], true)) {
            $id = null;
            if ($host === 'youtu.be') {
                $id = trim($path, '/');
            } elseif (! empty($query['v'])) {
                $id = $query['v'];
            } elseif (preg_match('#^/(embed|shorts|live|v)/([^/?]+)#', $path, $m)) {
                $id = $m[2];
            }
            $id = is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;

            return ['provider' => 'youtube', 'kind' => 'video', 'embed_id' => $id];
        }
        if (in_array($host, ['instagram.com', 'instagr.am'], true)) {
            $id = preg_match('#^/(?:[A-Za-z0-9_.]+/)?(p|reel|tv)/([A-Za-z0-9_-]{5,40})#', $path, $m) ? $m[1].'/'.$m[2] : null;

            return ['provider' => 'instagram', 'kind' => 'publicacao', 'embed_id' => $id];
        }
        if (in_array($host, ['facebook.com', 'fb.watch', 'fb.com'], true)) {
            return ['provider' => 'facebook', 'kind' => 'publicacao', 'embed_id' => null];
        }

        return ['provider' => 'site', 'kind' => 'reportagem', 'embed_id' => null];
    }

    /**
     * @return array{url: string, provider: string, kind: string, embed_id: ?string, title: ?string,
     *     description: ?string, source_name: ?string, suggested_image_url: ?string,
     *     preview_status: string, preview_message: ?string}
     */
    public function analyze(string $url): array
    {
        $url = trim($url);
        $this->fetcher->validateUrl($url);
        $detected = $this->detect($url);
        $base = $detected + [
            'url' => $url, 'title' => null, 'description' => null, 'source_name' => null,
            'suggested_image_url' => null, 'preview_status' => 'manual', 'preview_message' => null,
        ];

        return match ($detected['provider']) {
            'youtube' => $this->youtube($base),
            'instagram' => array_merge($base, [
                'source_name' => 'Instagram',
                'preview_message' => 'O Instagram não fornece título nem miniatura para outros sites. Informe um título e, se quiser, envie uma imagem autorizada. A publicação abre no Instagram.',
            ]),
            'facebook' => array_merge($base, [
                'source_name' => 'Facebook',
                'preview_message' => 'Prévias do Facebook não são obtidas automaticamente. Informe título e imagem manualmente.',
            ]),
            default => $this->website($base),
        };
    }

    private function youtube(array $base): array
    {
        $base['source_name'] = 'YouTube';
        if (! $base['embed_id']) {
            $base['preview_message'] = 'Não foi possível identificar o vídeo. O link será exibido como cartão.';

            return $base;
        }
        $endpoint = 'https://www.youtube.com/oembed?format=json&url='.rawurlencode('https://www.youtube.com/watch?v='.$base['embed_id']);
        try {
            $response = $this->fetcher->get($endpoint, ['application/json', 'text/json', 'text/javascript'], 200_000, 'application/json');
            $data = json_decode($response['body'], true);
            if (! is_array($data)) {
                throw new FetchException('Resposta inválida do YouTube.');
            }
            $base['title'] = $this->clean($data['title'] ?? null, 300);
            $author = $this->clean($data['author_name'] ?? null, 120);
            $base['source_name'] = $author ? "YouTube · {$author}" : 'YouTube';
            $thumb = $data['thumbnail_url'] ?? null;
            $base['suggested_image_url'] = is_string($thumb) && preg_match('#^https://#', $thumb) ? $thumb : null;
            $base['preview_status'] = 'ok';
        } catch (FetchException $e) {
            $base['preview_status'] = 'failed';
            $base['preview_message'] = 'O YouTube não informou os dados do vídeo ('.$e->getMessage().'). Preencha o título manualmente; o link continuará abrindo no YouTube.';
        }

        return $base;
    }

    private function website(array $base): array
    {
        try {
            $response = $this->fetcher->get($base['url'], ['text/html', 'application/xhtml+xml'], 1_500_000);
        } catch (FetchException $e) {
            $base['preview_status'] = 'failed';
            $base['preview_message'] = 'Prévia indisponível: '.$e->getMessage().' Preencha título e fonte manualmente; o link continuará abrindo a origem.';

            return $base;
        }

        $meta = $this->parseHtml($response['body'], $response['content_type']);
        $base['title'] = $meta['og:title'] ?? $meta['twitter:title'] ?? $meta['title'] ?? null;
        $base['description'] = $meta['og:description'] ?? $meta['twitter:description'] ?? $meta['description'] ?? null;
        $base['source_name'] = $meta['og:site_name'] ?? $meta['application-name'] ?? null;
        $image = $meta['og:image:secure_url'] ?? $meta['og:image'] ?? $meta['twitter:image'] ?? null;
        if ($image) {
            $image = $this->fetcher->absolutize($image, $response['url']);
            $base['suggested_image_url'] = preg_match('#^https?://#i', $image) && strlen($image) <= 2048 ? $image : null;
        }
        $base['title'] = $this->clean($base['title'], 300);
        $base['description'] = $this->clean($base['description'], 600);
        $base['source_name'] = $this->clean($base['source_name'], 120)
            ?: preg_replace('/^www\./', '', (string) parse_url($response['url'], PHP_URL_HOST));

        $found = array_filter([$base['title'], $base['description'], $base['suggested_image_url']]);
        $base['preview_status'] = $base['title'] ? (count($found) === 3 ? 'ok' : 'partial') : 'failed';
        if ($base['preview_status'] !== 'ok') {
            $base['preview_message'] = $base['title']
                ? 'O site forneceu apenas parte dos dados. Complete o que faltar manualmente.'
                : 'O site não forneceu título. Preencha os dados manualmente.';
        }

        return $base;
    }

    /** @return array<string, string> */
    public function parseHtml(string $html, string $contentType = ''): array
    {
        $html = substr($html, 0, 800_000);
        $charset = null;
        if (preg_match('/charset=([\w-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', $html, $m)) {
            $charset = $m[1];
        }
        if ($charset && strtolower($charset) !== 'utf-8' && in_array(strtoupper($charset), mb_list_encodings(), true)) {
            $html = mb_convert_encoding($html, 'UTF-8', $charset);
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $meta = [];
        foreach ($xpath->query('//meta') ?: [] as $node) {
            $key = strtolower(trim($node->getAttribute('property') ?: $node->getAttribute('name')));
            $content = trim($node->getAttribute('content'));
            if ($key !== '' && $content !== '' && ! isset($meta[$key])) {
                $meta[$key] = $content;
            }
        }
        $title = $xpath->query('//title')?->item(0)?->textContent;
        if ($title) {
            $meta['title'] = $title;
        }

        return $meta;
    }

    private function clean(?string $text, int $limit): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : Str::limit($text, $limit);
    }
}
