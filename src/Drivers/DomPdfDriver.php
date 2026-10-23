<?php

declare(strict_types=1);

namespace Vellum\Drivers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Capability;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Exceptions\DriverNotSupportedException;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Support\PageHtml;

/**
 * Renders the PDF with DOMPDF.
 */
final class DomPdfDriver implements Driver
{
    public function name(): string
    {
        return 'dompdf';
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return Capability::all();
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function render(Source $source, ResolvedConfig $config): string
    {
        if (! class_exists(Dompdf::class)) {
            throw DriverNotSupportedException::missingEngine($this->name(), 'dompdf/dompdf');
        }

        $html = $source->load($config);

        $html = PageHtml::injectMargins($html, $config->margins);
        $html = PageHtml::applyPaperSize($html, $config->paper, $config->orientation);

        if ($config->font !== null && $config->font !== '') {
            $html = PageHtml::applyFontFamily($html, $config->font);
        }

        if ($config->fontFile !== null && is_file($config->fontFile)) {
            $html = PageHtml::injectFont($html, (string) $config->font, $config->fontFile);
        }

        $options = new Options;
        $options->set('defaultPaperSize', $config->paper->value);
        $options->set('defaultFont', $config->font !== null && $config->font !== '' ? $config->font : 'serif');
        $options->set('dpi', $config->dpi);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);

        if ($config->tempFolder !== null) {
            $options->set('tempDir', $config->tempFolder);
        }

        if ($config->fontCachePath !== null) {
            $options->set('fontDir', $config->fontCachePath);
        }

        foreach ($config->options as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $options->set((string) $key, $value);
            }
        }

        // The document level settings always win over the engine options.
        $options->set('isRemoteEnabled', $config->allowRemoteAssets);
        $options->set('defaultPaperSize', $config->paper->value);

        foreach ($config->documentOptions as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $options->set((string) $key, $value);
            }
        }

        try {
            $dompdf = new Dompdf($options);
            $dompdf->setPaper($config->paper->value, $config->orientation->value);
            $basePath = $config->documentOptions['base_path'] ?? $config->options['base_path'] ?? '';

            $dompdf->loadHtml($html, is_string($basePath) ? $basePath : '');
            $dompdf->render();

            $output = $dompdf->output();

            if (! is_string($output) || $output === '') {
                throw new PdfGenerationException('DOMPDF returned an empty document.');
            }

            return $output;
        } catch (Throwable $exception) {
            if ($exception instanceof PdfGenerationException) {
                throw $exception;
            }

            throw PdfGenerationException::driverFailed($this->name(), $exception->getMessage(), $exception);
        }
    }
}
