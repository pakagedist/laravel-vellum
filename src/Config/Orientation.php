<?php

declare(strict_types=1);

namespace Vellum\Config;

use Vellum\Exceptions\InvalidConfigurationException;

enum Orientation: string
{
    case Portrait = 'portrait';
    case Landscape = 'landscape';

    public function isLandscape(): bool
    {
        return $this === self::Landscape;
    }

    public static function parse(string $value): self
    {
        $normalized = strtolower(trim($value));

        $case = self::tryFrom($normalized);

        if ($case instanceof self) {
            return $case;
        }

        throw InvalidConfigurationException::unsupportedOrientation($value);
    }
}
