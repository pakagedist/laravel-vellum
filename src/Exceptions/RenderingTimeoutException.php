<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

/**
 * Thrown when rendering exceeds the configured timeout.
 */
class RenderingTimeoutException extends PdfGenerationException
{
    public static function exceeded(string $driver, int $timeout): self
    {
        return new self(sprintf('The [%s] driver exceeded the configured timeout of %d seconds.', $driver, $timeout));
    }
}
