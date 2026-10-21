<?php

declare(strict_types=1);

namespace Vellum\Sources;

use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;

/**
 * Uses a plain HTML string as the content of the document.
 */
final class HtmlSource implements Source
{
    private function __construct(
        private readonly string $html,
    ) {}

    public static function make(string $html): self
    {
        return new self($html);
    }

    public function type(): string
    {
        return 'html';
    }

    public function describe(): string
    {
        return 'inline-html';
    }

    public function load(ResolvedConfig $config): string
    {
        return $this->html;
    }
}
