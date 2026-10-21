<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * An indirect object of a PDF document.
 */
final class PdfObject
{
    /**
     * @param  array<string, string>  $dictionary
     */
    public function __construct(
        public readonly int $number,
        public readonly int $generation,
        public readonly array $dictionary,
        public readonly ?string $stream = null,
    ) {}

    public function has(string $key): bool
    {
        return isset($this->dictionary[$key]);
    }

    public function get(string $key): ?string
    {
        return $this->dictionary[$key] ?? null;
    }
}
