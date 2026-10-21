<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

/**
 * Thrown when the configuration of the package is not usable.
 */
class InvalidConfigurationException extends PdfGenerationException
{
    public static function unsupportedPaperSize(string $paper): self
    {
        return new self(sprintf('Unsupported paper size [%s].', $paper));
    }

    public static function unsupportedOrientation(string $orientation): self
    {
        return new self(sprintf('Unsupported orientation [%s].', $orientation));
    }

    public static function missingDriver(string $driver): self
    {
        return new self(sprintf('A PDF driver name is required, [%s] is not a valid name.', $driver));
    }

    public static function invalidType(string $key, string $expected, mixed $value): self
    {
        return new self(sprintf(
            'The configuration value [%s] must be %s, %s given.',
            $key,
            $expected,
            get_debug_type($value),
        ));
    }
}
