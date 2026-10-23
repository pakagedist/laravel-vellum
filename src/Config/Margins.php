<?php

declare(strict_types=1);

namespace Vellum\Config;

/**
 * Page margins expressed in millimetres.
 */
final class Margins
{
    public function __construct(
        public readonly float $top = 0.0,
        public readonly float $right = 0.0,
        public readonly float $bottom = 0.0,
        public readonly float $left = 0.0,
    ) {}

    /**
     * @param  array<string, float|int|string>  $margins
     */
    public static function fromArray(array $margins): self
    {
        return new self(
            (float) ($margins['top'] ?? 0.0),
            (float) ($margins['right'] ?? 0.0),
            (float) ($margins['bottom'] ?? 0.0),
            (float) ($margins['left'] ?? 0.0),
        );
    }

    /**
     * @return array{top: float, right: float, bottom: float, left: float}
     */
    public function toArray(): array
    {
        return [
            'top' => $this->top,
            'right' => $this->right,
            'bottom' => $this->bottom,
            'left' => $this->left,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->top === 0.0 && $this->right === 0.0 && $this->bottom === 0.0 && $this->left === 0.0;
    }

    /**
     * The CSS representation used when injecting @page rules in the HTML.
     */
    public function toCss(): string
    {
        return sprintf(
            '%smm %smm %smm %smm',
            $this->top,
            $this->right,
            $this->bottom,
            $this->left,
        );
    }
}
