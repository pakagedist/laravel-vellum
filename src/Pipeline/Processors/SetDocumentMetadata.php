<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use Vellum\Contracts\Processor;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\DocumentMetadata;
use Vellum\Support\Pdf\PdfEditor;

/**
 * Writes the document metadata in the /Info dictionary.
 */
final class SetDocumentMetadata implements Processor
{
    public function __construct(
        private readonly DocumentMetadata $metadata,
    ) {}

    public function name(): string
    {
        return 'metadata';
    }

    public function process(PdfContext $context): PdfContext
    {
        return $context->withBytes(PdfEditor::setMetadata($context->bytes, $this->metadata));
    }
}
