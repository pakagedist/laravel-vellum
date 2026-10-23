<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use Vellum\Contracts\Processor;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\Pdf\PdfEditor;

/**
 * Embeds an XMP metadata packet in the document.
 */
final class EmbedXmpMetadata implements Processor
{
    public function __construct(
        private readonly string $packet,
    ) {}

    public function name(): string
    {
        return 'xmp';
    }

    public function process(PdfContext $context): PdfContext
    {
        return $context->withBytes(PdfEditor::setXmp($context->bytes, $this->packet));
    }
}
