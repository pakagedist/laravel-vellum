<?php

declare(strict_types=1);

namespace Vellum\Pipeline;

use Vellum\Config\ResolvedConfig;

/**
 * The state shared by the processors of a pipeline.
 */
final class PdfContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $bytes,
        public readonly ResolvedConfig $config,
        public readonly array $attributes = [],
    ) {}

    public function withBytes(string $bytes): self
    {
        return new self($bytes, $this->config, $this->attributes);
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self($this->bytes, $this->config, $attributes);
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
