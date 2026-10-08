<?php

namespace App\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;

/**
 * Texto formatado seguro: Markdown com HTML bruto escapado, links inseguros
 * (javascript:, data:) removidos, imagens externas convertidas em texto e
 * títulos rebaixados para não competir com o título da página.
 */
class Markdown
{
    private static ?MarkdownConverter $converter = null;

    public static function render(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }

        return (string) self::converter()->convert($text);
    }

    private static function converter(): MarkdownConverter
    {
        if (self::$converter) {
            return self::$converter;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
            'external_link' => [
                'internal_hosts' => [$host],
                'open_in_new_window' => false,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) {
            $walker = $event->getDocument()->walker();
            $images = [];
            while ($walkerEvent = $walker->next()) {
                $node = $walkerEvent->getNode();
                if ($walkerEvent->isEntering() && $node instanceof Heading) {
                    $node->setLevel(min(6, max(2, $node->getLevel() + 1)));
                }
                if ($walkerEvent->isEntering() && $node instanceof Image) {
                    $images[] = $node;
                }
            }
            foreach ($images as $image) {
                $label = trim(implode('', array_map(
                    fn ($child) => $child instanceof Text ? $child->getLiteral() : '',
                    iterator_to_array($image->children())
                )));
                $image->replaceWith(new Text($label !== '' ? "[imagem: {$label}]" : '[imagem]'));
            }
        });

        return self::$converter = new MarkdownConverter($environment);
    }

    /** Texto puro para resumos, metadados e busca. */
    public static function plain(?string $text, int $limit = 0): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags(self::render($text))) ?? '');
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $limit > 0 ? \Illuminate\Support\Str::limit($plain, $limit) : $plain;
    }
}
