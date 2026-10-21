<?php

declare(strict_types=1);

namespace Vellum\Contracts;

use Vellum\Exceptions\PdfGenerationException;
use Vellum\Pipeline\PdfContext;

/**
 * A post processor transforms the PDF bytes after rendering.
 *
 * Implement this contract to plug your own transformations.
 */
interface Processor
{
    /**
     * The processor name, used for logging and error messages.
     */
    public function name(): string;

    /**
     * @throws PdfGenerationException
     */
    public function process(PdfContext $context): PdfContext;
}
