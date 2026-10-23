<?php

declare(strict_types=1);

namespace Vellum\Support\Pdf;

/**
 * The cross reference table of a PDF document.
 */
final class XrefInfo
{
    /**
     * @param  array<int, array{type: int, offset: int, generation: int}>  $entries
     * @param  array<string, string>  $trailer
     */
    public function __construct(
        public readonly array $entries,
        public readonly bool $isStream,
        public readonly array $trailer,
    ) {}

    public function offsetOf(int $number): ?int
    {
        $entry = $this->entries[$number] ?? null;

        if ($entry === null || $entry['type'] !== 1) {
            return null;
        }

        return $entry['offset'];
    }

    public function size(): int
    {
        $size = isset($this->trailer['Size']) ? (int) $this->trailer['Size'] : 0;

        foreach (array_keys($this->entries) as $number) {
            $size = max($size, $number + 1);
        }

        return $size;
    }

    public function maxObjectNumber(): int
    {
        $max = 0;

        foreach (array_keys($this->entries) as $number) {
            $max = max($max, $number);
        }

        return $max;
    }
}
