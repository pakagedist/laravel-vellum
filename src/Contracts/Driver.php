<?php

declare(strict_types=1);

namespace Vellum\Contracts;

use Vellum\Config\ResolvedConfig;
use Vellum\Exceptions\PdfGenerationException;

/**
 * A driver turns a source into raw PDF bytes.
 *
 * Implement this contract to plug your own engine.
 */
interface Driver
{
    /**
     * The driver name, as used by `->driver('name')` and the config file.
     */
    public function name(): string;

    /**
     * Render the given source and return the raw PDF bytes.
     *
     * @throws PdfGenerationException
     */
    public function render(Source $source, ResolvedConfig $config): string;

    /**
     * The capabilities natively supported by the engine.
     *
     * @return list<string>
     */
    public function capabilities(): array;

    public function supports(string $capability): bool;
}
