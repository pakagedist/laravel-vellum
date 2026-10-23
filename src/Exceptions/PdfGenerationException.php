<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception for every failure raised by the package.
 */
class PdfGenerationException extends RuntimeException
{
    public static function driverFailed(string $driver, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('The [%s] driver failed to render the PDF: %s', $driver, $reason), 0, $previous);
    }

    public static function processingFailed(string $processor, string $reason, ?Throwable $previous = null): self
    {
        return new self(sprintf('The [%s] post processor failed: %s', $processor, $reason), 0, $previous);
    }

    /**
     * @param  list<string>  $warnings
     */
    public static function warningsRaised(string $driver, array $warnings): self
    {
        return new self(sprintf(
            'The [%s] driver raised %d warning(s): %s',
            $driver,
            count($warnings),
            implode(' | ', $warnings),
        ));
    }
}
