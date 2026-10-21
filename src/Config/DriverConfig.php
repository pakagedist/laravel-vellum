<?php

declare(strict_types=1);

namespace Vellum\Config;

/**
 * The configuration of a single driver.
 */
final class DriverConfig
{
    public function __construct(
        public readonly string $name,
        public readonly DocumentOptions $options,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self($name, DocumentOptions::fromArray($config));
    }
}
