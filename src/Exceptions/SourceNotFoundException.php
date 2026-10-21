<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

/**
 * Thrown when a source cannot be located or loaded (missing template, missing
 * file, unreachable URL, missing rendering library...).
 */
class SourceNotFoundException extends PdfGenerationException
{
    public static function missingView(string $view): self
    {
        return new self(sprintf('The view [%s] could not be found.', $view));
    }

    public static function missingFile(string $path): self
    {
        return new self(sprintf('The file [%s] could not be found or is not readable.', $path));
    }

    public static function missingUrl(string $url, string $reason = ''): self
    {
        return new self(rtrim(sprintf('The URL [%s] could not be loaded. %s', $url, $reason)));
    }

    public static function missingLibrary(string $library, string $source): self
    {
        return new self(sprintf(
            'The [%s] source requires the "%s" library. Install it with: composer require %s',
            $source,
            $library,
            $library,
        ));
    }
}
