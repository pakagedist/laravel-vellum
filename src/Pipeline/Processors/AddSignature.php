<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use Vellum\Contracts\Processor;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\Pdf\PdfEditor;

/**
 * Signs the document with a PKCS#12 certificate.
 */
final class AddSignature implements Processor
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        private readonly string $certificatePath,
        private readonly string $password = '',
        private readonly array $options = [],
    ) {}

    public function name(): string
    {
        return 'signature';
    }

    public function process(PdfContext $context): PdfContext
    {
        return $context->withBytes(
            PdfEditor::sign($context->bytes, $this->certificatePath, $this->password, $this->options),
        );
    }
}
