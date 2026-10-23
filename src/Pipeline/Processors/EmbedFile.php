<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use Vellum\Contracts\Processor;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Pipeline\PdfContext;
use Vellum\Support\Pdf\PdfEditor;

/**
 * Embeds a file inside the document.
 */
final class EmbedFile implements Processor
{
    public function __construct(
        private readonly string $path,
        private readonly ?string $name = null,
        private readonly string $relationship = 'Data',
        private readonly string $mimeType = 'text/xml',
    ) {}

    public function name(): string
    {
        return 'embed-file';
    }

    public function process(PdfContext $context): PdfContext
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw new PdfGenerationException(sprintf('The file [%s] could not be read.', $this->path));
        }

        $content = (string) file_get_contents($this->path);
        $name = $this->name ?? basename($this->path);

        return $context->withBytes(
            PdfEditor::embedFile($context->bytes, $name, $content, $this->relationship, $this->mimeType),
        );
    }
}
