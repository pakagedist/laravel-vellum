<?php

declare(strict_types=1);

namespace Vellum\Pipeline\Processors;

use setasign\Fpdi\Fpdi;
use Throwable;
use Vellum\Contracts\Processor;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Pipeline\PdfContext;

/**
 * Merges other PDF documents into the rendered one.
 */
final class MergePdfs implements Processor
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        private readonly array $paths,
    ) {}

    public function name(): string
    {
        return 'merge';
    }

    public function process(PdfContext $context): PdfContext
    {
        if ($this->paths === []) {
            return $context;
        }

        if (! class_exists(Fpdi::class)) {
            throw new PdfGenerationException(
                'The merge post processor requires the "setasign/fpdi" package. Install it with: composer require setasign/fpdi',
            );
        }

        $sources = array_merge([$this->temporaryFile($context->bytes)], $this->validatedPaths());

        try {
            $pdf = new Fpdi;

            foreach ($sources as $source) {
                $pageCount = $pdf->setSourceFile($source);

                for ($page = 1; $page <= $pageCount; $page++) {
                    $template = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($template);

                    $pdf->AddPage(
                        $size['orientation'] ?? 'P',
                        [$size['width'] ?? 210.0, $size['height'] ?? 297.0],
                    );

                    $pdf->useTemplate($template);
                }
            }

            $output = $pdf->Output('S', 'merged.pdf');

            if (! is_string($output) || $output === '') {
                throw new PdfGenerationException('The merge post processor returned an empty document.');
            }

            return $context->withBytes($output);
        } catch (PdfGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw PdfGenerationException::processingFailed($this->name(), $exception->getMessage(), $exception);
        } finally {
            foreach ($this->temporaryFiles as $file) {
                @unlink($file);
            }

            $this->temporaryFiles = [];
        }
    }

    /**
     * @var list<string>
     */
    private array $temporaryFiles = [];

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vellum-merge-');

        if ($path === false) {
            throw new PdfGenerationException('Unable to create a temporary file for the merge post processor.');
        }

        file_put_contents($path, $content);

        $this->temporaryFiles[] = $path;

        return $path;
    }

    /**
     * @return list<string>
     */
    private function validatedPaths(): array
    {
        $paths = [];

        foreach ($this->paths as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                throw new PdfGenerationException(sprintf('The PDF [%s] could not be read.', $path));
            }

            $paths[] = $path;
        }

        return $paths;
    }
}
