<?php

declare(strict_types=1);

namespace Vellum\Exceptions;

/**
 * Thrown when a driver is unknown or when its engine is not installed.
 */
class DriverNotSupportedException extends PdfGenerationException
{
    public static function unknown(string $driver): self
    {
        return new self(sprintf('The PDF driver [%s] is not registered.', $driver));
    }

    public static function missingEngine(string $driver, string $package): self
    {
        return new self(sprintf(
            'The [%s] driver requires the "%s" package. Install it with: composer require %s',
            $driver,
            $package,
            $package,
        ));
    }

    public static function missingCapability(string $driver, string $capability): self
    {
        return new self(sprintf('The [%s] driver does not support the [%s] capability.', $driver, $capability));
    }
}
