<?php

declare(strict_types=1);

namespace Vellum\Support;

use Vellum\Config\Margins;
use Vellum\Config\Orientation;
use Vellum\Config\PaperSize;
use Vellum\Exceptions\InvalidConfigurationException;

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
     * Remove the `size` declarations of the @page rules.
     *
     * The engines do not read the property in the same way: mPDF parses a
     * paper name as a zero sized page (and the layout collapses), DOMPDF
     * lets the template override the configured paper and Chromium ignores
     * the rule. The paper size always comes from the document configuration,
     * so the declarations found in the templates are removed.
     */
    public static function stripPageSize(string $html): string
    {
        return (string) preg_replace_callback(
            '/(@page\b[^{]*\{)([^}]*)\}/i',
            static function (array $matches): string {
                $body = (string) preg_replace('/(?<![\w-])size\s*:[^;}]*;?/i', '', $matches[2]);
                $body = (string) preg_replace('/;\s*;/', ';', $body);

                if (trim($body, " \t\r\n;") === '') {
                    return '';
                }

                return $matches[1].$body.'}';
            },
            $html,
        );
    }

    /**
     * The paper sizes declared in the @page rules of the document.
     *
     * @return list<string>
     */
    public static function pageSizes(string $html): array
    {
        $sizes = [];

        if (preg_match_all('/@page\b[^{]*\{([^}]*)\}/i', $html, $blocks) === 0) {
            return [];
        }

        foreach ($blocks[1] as $body) {
            if (preg_match_all('/(?<![\w-])size\s*:\s*([^;}]*)/i', $body, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $value) {
                $value = trim($value);

                if ($value !== '') {
                    $sizes[] = $value;
                }
            }
        }

        return $sizes;
    }

    /**
     * The @page sizes that do not match the paper size of the document.
     *
     * @return list<string>
     */
    public static function conflictingPageSizes(string $html, PaperSize $paper, Orientation $orientation): array
    {
        $conflicts = [];

        foreach (self::pageSizes($html) as $value) {
            $tokens = preg_split('/\s+/', strtolower(trim($value))) ?: [];

            $declaredOrientation = null;
            $declaredPaper = null;

            foreach ($tokens as $token) {
                if ($token === 'landscape' || $token === 'portrait') {
                    $declaredOrientation = $token;

                    continue;
                }

                if ($token === '' || $token === 'auto') {
                    continue;
                }

                $declaredPaper = $token;
            }

            if ($declaredPaper !== null) {
                try {
                    $parsed = PaperSize::parse($declaredPaper);
                } catch (InvalidConfigurationException) {
                    $conflicts[] = $value;

                    continue;
                }

                if ($parsed !== $paper) {
                    $conflicts[] = $value;

                    continue;
                }

                // A paper without an orientation keyword means portrait.
                $declaredOrientation ??= 'portrait';
            }

            if ($declaredOrientation !== null) {
                $expected = $orientation->isLandscape() ? 'landscape' : 'portrait';

                if ($declaredOrientation !== $expected) {
                    $conflicts[] = $value;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Apply the paper size of the document to the HTML.
     *
     * The @page size declarations of the templates are removed, because every
     * engine reads them in a different way, and a warning is raised when one
     * of them does not match the configured paper.
     */
    public static function applyPaperSize(string $html, PaperSize $paper, Orientation $orientation): string
    {
        $conflicts = self::conflictingPageSizes($html, $paper, $orientation);

        $html = self::stripPageSize($html);

        foreach ($conflicts as $conflict) {
            trigger_error(sprintf(
                'The @page size "%s" was ignored: the paper size comes from the document configuration (%s).',
                $conflict,
                $paper->label(),
            ), E_USER_WARNING);
        }

        return $html;
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
