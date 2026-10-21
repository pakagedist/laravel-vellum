<?php

declare(strict_types=1);

namespace Vellum\Support;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The immutable result of a render: the bytes plus the metrics of the run.
 */
final class PdfResult
{
    /**
     * @param  list<string>  $warnings  The warnings raised by the engine.
     */
    public function __construct(
        public readonly string $bytes,
        public readonly string $driver,
        public readonly string $sourceType,
        public readonly float $durationMs,
        public readonly ?string $filename = null,
        public readonly int $size = 0,
        public readonly array $warnings = [],
    ) {}

    public function contentType(): string
    {
        return 'application/pdf';
    }

    public function size(): int
    {
        return $this->size !== 0 ? $this->size : strlen($this->bytes);
    }

    public function md5(): string
    {
        return md5($this->bytes);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->bytes);
    }

    public function toBase64(): string
    {
        return base64_encode($this->bytes);
    }

    /**
     * Save the PDF on disk and return the path.
     */
    public function save(string $path): string
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create the directory [%s].', $directory));
        }

        if (file_put_contents($path, $this->bytes) === false) {
            throw new \RuntimeException(sprintf('Unable to write the PDF to [%s].', $path));
        }

        return $path;
    }

    /**
     * Stream the PDF in the browser.
     */
    public function stream(): StreamedResponse
    {
        return new StreamedResponse(
            function (): void {
                echo $this->bytes;
            },
            200,
            [
                'Content-Type' => $this->contentType(),
                'Content-Length' => (string) $this->size(),
                'Content-Disposition' => 'inline; filename="'.addslashes($this->filename ?? 'document.pdf').'"',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'Pragma' => 'public',
            ],
        );
    }

    /**
     * Force the browser to download the PDF.
     */
    public function download(?string $filename = null): Response
    {
        $name = $filename ?? $this->filename ?? 'document.pdf';

        $response = new Response($this->bytes, 200, [
            'Content-Type' => $this->contentType(),
            'Content-Length' => (string) $this->size(),
            'Content-Disposition' => 'attachment; filename="'.addslashes($name).'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);

        return $response;
    }

    /**
     * Stream the PDF from a temporary file, keeping memory usage low for big
     * documents.
     */
    public function downloadFromTemporaryFile(?string $filename = null): BinaryFileResponse
    {
        $name = $filename ?? $this->filename ?? 'document.pdf';

        $path = tempnam(sys_get_temp_dir(), 'vellum-');

        if ($path === false) {
            throw new \RuntimeException('Unable to create a temporary file for the PDF response.');
        }

        file_put_contents($path, $this->bytes);

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $this->contentType());
        $response->headers->set('Content-Disposition', 'attachment; filename="'.addslashes($name).'"');
        $response->deleteFileAfterSend(true);

        return $response;
    }
}
