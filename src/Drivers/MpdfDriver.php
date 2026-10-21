<?php

declare(strict_types=1);

namespace Vellum\Drivers;

use Mpdf\Mpdf;
use Throwable;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Capability;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Exceptions\DriverNotSupportedException;
use Vellum\Exceptions\PdfGenerationException;

/**
 * Renders the PDF with mPDF.
 */
final class MpdfDriver implements Driver
{
    public function name(): string
    {
        return 'mpdf';
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
        if (! class_exists(Mpdf::class)) {
            throw DriverNotSupportedException::missingEngine($this->name(), 'mpdf/mpdf');
        }

        $html = $source->load($config);

        $options = [
            'format' => $config->paper->label(),
            'orientation' => $config->orientation->isLandscape() ? 'L' : 'P',
            'dpi' => $config->dpi,
        ];

        if ($config->tempFolder !== null) {
            $options['tempDir'] = $config->tempFolder;
        }

        if ($config->fontCachePath !== null) {
            $options['fontDir'] = $config->fontCachePath;
        }

        if ($config->font !== null && $config->font !== '') {
            $options['default_font'] = $config->font;
        }

        foreach ($config->options as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $options[(string) $key] = $value;
            }
        }

        // The document level settings always win over the engine options.
        $options['format'] = $config->paper->label();
        $options['orientation'] = $config->orientation->isLandscape() ? 'L' : 'P';
        $options['dpi'] = $config->dpi;

        foreach ($config->documentOptions as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $options[(string) $key] = $value;
            }
        }

        try {
            $mpdf = new Mpdf($options);

            $mpdf->SetMargins($config->margins->left, $config->margins->right, $config->margins->top);
            $mpdf->SetAutoPageBreak(true, $config->margins->bottom);
            $mpdf->WriteHTML($html);

            $output = $mpdf->Output('', 'S');

            if (! is_string($output) || $output === '') {
                throw new PdfGenerationException('mPDF returned an empty document.');
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
