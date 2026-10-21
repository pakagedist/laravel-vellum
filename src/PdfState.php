<?php

declare(strict_types=1);

namespace Vellum;

use Vellum\Config\DocumentOptions;
use Vellum\Contracts\Processor;
use Vellum\Contracts\Source;
use Vellum\Support\DocumentMetadata;

/**
 * The mutable state of a document, copied on every change so the builder can
 * stay immutable.
 */
final class PdfState
{
    /**
     * @param  array<string, string>  $xmpProperties
     * @param  array<string, string>  $xmpNamespaces
     * @param  list<array{path: string, name: ?string, relationship: string, mimeType: string}>  $embeddedFiles
     * @param  list<string>  $mergePaths
     * @param  array{certificate: string, password: string, options: array<string, mixed>}|null  $signature
     * @param  list<Processor>  $processors
     */
    public function __construct(
        public Source $source,
        public ?string $driver = null,
        public DocumentOptions $overrides = new DocumentOptions,
        public ?DocumentMetadata $metadata = null,
        public array $xmpProperties = [],
        public array $xmpNamespaces = [],
        public bool $pdfA = false,
        public ?string $iccProfile = null,
        public array $embeddedFiles = [],
        public array $mergePaths = [],
        public ?array $signature = null,
        public array $processors = [],
        public ?string $filename = null,
    ) {}
}
