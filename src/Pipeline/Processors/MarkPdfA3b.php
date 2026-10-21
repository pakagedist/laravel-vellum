<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use Vellum\Contracts\Processor;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\Pdf\PdfEditor;

/**
 * Marks the document as PDF/A-3b: identification metadata, output intent and
 * a stable document identifier.
 */
final class MarkPdfA3b implements Processor
{
    public function __construct(
        private readonly string $packet,
        private readonly ?string $iccProfile = null,
        private readonly ?string $documentId = null,
    ) {}

    public function name(): string
    {
        return 'pdfa-3b';
    }

    public function process(PdfContext $context): PdfContext
    {
        $documentId = $this->documentId ?? substr(md5($context->bytes), 0, 32);

        return $context->withBytes(
            PdfEditor::markPdfA3b($context->bytes, $this->packet, $this->iccProfile, $documentId),
        );
    }
}
