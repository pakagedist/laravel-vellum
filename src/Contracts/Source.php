<?php

declare(strict_types=1);

namespace Vellum\Contracts;

use Vellum\Config\ResolvedConfig;
use Vellum\Exceptions\SourceNotFoundException;

/**
 * A source is anything that can produce the HTML used to render a PDF.
 *
 * Implement this contract to plug your own content providers.
 */
interface Source
{
    /**
     * The source type, used for logging and reporting ("blade", "html"...).
     */
    public function type(): string;

    /**
     * A human readable description of the source (a view name, a path...).
     */
    public function describe(): string;

    /**
     * Resolve the source into its HTML representation.
     *
     * @throws SourceNotFoundException
     */
    public function load(ResolvedConfig $config): string;
}
