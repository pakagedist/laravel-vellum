<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Config\Margins;

/**
 * Helpers to apply page level settings to the generated HTML.
 */
final class PageHtml
{
    /**
     * Inject an @page rule so DOMPDF honours the configured margins.
     *
     * The other engines receive the margins through their own API, so they
     * must not use this helper or the margins would be applied twice.
     */
    public static function injectMargins(string $html, Margins $margins): string
    {
        if ($margins->isEmpty()) {
            return $html;
        }

        return self::injectStyle($html, sprintf('@page{margin:%s;}', $margins->toCss()));
    }

    /**
     * Inject an @font-face rule for a local font file.
     */
    public static function injectFont(string $html, string $family, string $file): string
    {
        $uri = str_starts_with($file, 'http://') || str_starts_with($file, 'https://')
            ? $file
            : 'file://'.(realpath($file) ?: $file);

        return self::injectStyle($html, sprintf(
            '@font-face{font-family:"%s";src:url("%s") format("truetype");}',
            $family,
            $uri,
        ));
    }

    /**
     * Apply the default font family to the document.
     */
    public static function applyFontFamily(string $html, string $family): string
    {
        return self::injectStyle($html, sprintf('html,body{font-family:"%s",sans-serif;}', $family));
    }

    private static function injectStyle(string $html, string $css): string
    {
        $style = sprintf('<style>%s</style>', $css);

        if (stripos($html, '</head>') !== false) {
            return (string) preg_replace('/<\/head>/i', $style.'</head>', $html, 1);
        }

        if (stripos($html, '<body') !== false) {
            return (string) preg_replace('/<body[^>]*>/i', '$0'.$style, $html, 1);
        }

        return $style.$html;
    }
}
