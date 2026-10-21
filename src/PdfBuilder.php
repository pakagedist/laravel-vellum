<?php

declare(strict_types=1);

namespace Vellum;

use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Vellum\Config\ConfigResolver;
use Vellum\Config\Margins;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Capability;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Processor;
use Vellum\Drivers\DriverRegistry;
use Vellum\Exceptions\DriverNotSupportedException;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Exceptions\RenderingTimeoutException;
use Vellum\Pipeline\PdfContext;
use Vellum\Pipeline\Pipeline;
use Vellum\Pipeline\Processors\AddSignature;
use Vellum\Pipeline\Processors\EmbedFile;
use Vellum\Pipeline\Processors\EmbedXmpMetadata;
use Vellum\Pipeline\Processors\MarkPdfA3b;
use Vellum\Pipeline\Processors\MergePdfs;
use Vellum\Pipeline\Processors\SetDocumentMetadata;
use Vellum\Support\DocumentMetadata;
use Vellum\Support\Pdf\XmpPacket;
use Vellum\Support\PdfResult;
use Vellum\Support\RenderGuard;
use Vellum\Support\WarningCollector;

/**
 * The fluent, immutable document builder.
 *
 * Every method returns a new instance: nothing is rendered until one of the
 * terminal methods (render, bytes, save, stream, download) is called.
 */
final class PdfBuilder
{
    public function __construct(
        private readonly PdfState $state,
        private readonly ConfigResolver $resolver,
        private readonly DriverRegistry $drivers,
        private readonly Pipeline $pipeline,
        private readonly LoggerInterface $logger,
    ) {}

    public function state(): PdfState
    {
        return $this->state;
    }

    /**
     * Select the driver used to render the document.
     */
    public function driver(string $name): self
    {
        return $this->with(static function (PdfState $state) use ($name): void {
            $state->driver = $name;
        });
    }

    /**
     * Set the paper size and, optionally, the orientation.
     */
    public function paper(string $size, ?string $orientation = null): self
    {
        return $this->with(static function (PdfState $state) use ($size, $orientation): void {
            $state->overrides = $state->overrides->withPaper($size);

            if ($orientation !== null) {
                $state->overrides = $state->overrides->withOrientation($orientation);
            }
        });
    }

    public function orientation(string $orientation): self
    {
        return $this->with(static function (PdfState $state) use ($orientation): void {
            $state->overrides = $state->overrides->withOrientation($orientation);
        });
    }

    /**
     * Set the default font family and, optionally, the font file to embed.
     */
    public function font(string $family, ?string $file = null): self
    {
        return $this->with(static function (PdfState $state) use ($family, $file): void {
            $state->overrides = $state->overrides->withFont($family, $file);
        });
    }

    public function dpi(int $dpi): self
    {
        return $this->with(static function (PdfState $state) use ($dpi): void {
            $state->overrides = $state->overrides->withDpi($dpi);
        });
    }

    /**
     * Set the page margins, in millimetres.
     */
    public function margins(float $top, float $right, float $bottom, float $left): self
    {
        return $this->with(static function (PdfState $state) use ($top, $right, $bottom, $left): void {
            $state->overrides = $state->overrides->withMargins(new Margins($top, $right, $bottom, $left));
        });
    }

    public function tempFolder(string $path): self
    {
        return $this->with(static function (PdfState $state) use ($path): void {
            $state->overrides = $state->overrides->withTempFolder($path);
        });
    }

    public function fontCachePath(string $path): self
    {
        return $this->with(static function (PdfState $state) use ($path): void {
            $state->overrides = $state->overrides->withFontCachePath($path);
        });
    }

    public function allowRemoteAssets(bool $allowed = true): self
    {
        return $this->with(static function (PdfState $state) use ($allowed): void {
            $state->overrides = $state->overrides->withAllowRemoteAssets($allowed);
        });
    }

    /**
     * Maximum number of seconds allowed to render the document.
     */
    public function timeout(int $seconds): self
    {
        return $this->with(static function (PdfState $state) use ($seconds): void {
            $state->overrides = $state->overrides->withTimeout($seconds);
        });
    }

    /**
     * Turn the warnings raised by the engine into an exception.
     */
    public function strictWarnings(bool $strict = true): self
    {
        return $this->with(static function (PdfState $state) use ($strict): void {
            $state->overrides = $state->overrides->withFailOnWarnings($strict);
        });
    }

    /**
     * Set an engine specific option.
     */
    public function option(string $key, mixed $value): self
    {
        return $this->with(static function (PdfState $state) use ($key, $value): void {
            $state->overrides = $state->overrides->withOption($key, $value);
        });
    }

    /**
     * Set the document metadata.
     *
     * @param  array<string, mixed>|DocumentMetadata  $metadata
     */
    public function metadata(array|DocumentMetadata $metadata): self
    {
        return $this->with(static function (PdfState $state) use ($metadata): void {
            $state->metadata = $metadata instanceof DocumentMetadata
                ? $metadata
                : DocumentMetadata::fromArray($metadata);
        });
    }

    /**
     * Add custom XMP properties.
     *
     * @param  array<string, string>  $properties
     */
    public function xmp(array $properties): self
    {
        return $this->with(static function (PdfState $state) use ($properties): void {
            $state->xmpProperties = array_merge($state->xmpProperties, $properties);
        });
    }

    /**
     * Declare an extra XMP namespace.
     */
    public function xmpNamespace(string $prefix, string $uri): self
    {
        return $this->with(static function (PdfState $state) use ($prefix, $uri): void {
            $state->xmpNamespaces[$prefix] = $uri;
        });
    }

    /**
     * Mark the document as PDF/A-3b.
     */
    public function pdfa(?string $iccProfile = null): self
    {
        return $this->with(static function (PdfState $state) use ($iccProfile): void {
            $state->pdfA = true;
            $state->iccProfile = $iccProfile ?? $state->iccProfile;
        });
    }

    /**
     * Embed a file inside the document.
     */
    public function embedFile(
        string $path,
        ?string $name = null,
        string $relationship = 'Data',
        string $mimeType = 'text/xml',
    ): self {
        return $this->with(static function (PdfState $state) use ($path, $name, $relationship, $mimeType): void {
            $state->embeddedFiles[] = [
                'path' => $path,
                'name' => $name,
                'relationship' => $relationship,
                'mimeType' => $mimeType,
            ];
        });
    }

    /**
     * Turn the document into a ZUGFeRD / Factur-X hybrid invoice.
     *
     * @param  array{document_type?: string, conformance_level?: string, version?: string, profile?: string, name?: string}  $options
     */
    public function zugferd(string $path, array $options = []): self
    {
        $documentType = (string) ($options['document_type'] ?? 'INVOICE');
        $conformance = (string) ($options['conformance_level'] ?? 'EN 16931');
        $version = (string) ($options['version'] ?? '1.0');
        $profile = (string) ($options['profile'] ?? 'urn:factur-x:pdfa:CrossIndustryDocument:invoice:1p0#');
        $name = (string) ($options['name'] ?? basename($path));

        return $this
            ->xmpNamespace('fx', $profile)
            ->xmp([
                'fx:DocumentType' => $documentType,
                'fx:DocumentFileName' => $name,
                'fx:ConformanceLevel' => $conformance,
                'fx:Version' => $version,
            ])
            ->pdfa()
            ->embedFile($path, $name, 'Alternative', 'text/xml');
    }

    /**
     * Merge other PDF documents into the rendered one.
     *
     * @param  string|list<string>  $paths
     */
    public function merge(string|array $paths): self
    {
        return $this->with(static function (PdfState $state) use ($paths): void {
            $state->mergePaths = array_merge(
                $state->mergePaths,
                is_array($paths) ? array_values($paths) : [$paths],
            );
        });
    }

    /**
     * Sign the document with a PKCS#12 certificate.
     *
     * @param  array<string, mixed>  $options
     */
    public function sign(string $certificate, string $password = '', array $options = []): self
    {
        return $this->with(static function (PdfState $state) use ($certificate, $password, $options): void {
            $state->signature = [
                'certificate' => $certificate,
                'password' => $password,
                'options' => $options,
            ];
        });
    }

    /**
     * Register a custom post processor.
     */
    public function process(Processor ...$processors): self
    {
        return $this->with(static function (PdfState $state) use ($processors): void {
            foreach ($processors as $processor) {
                $state->processors[] = $processor;
            }
        });
    }

    /**
     * The name proposed to the browser when the document is streamed.
     */
    public function filename(string $filename): self
    {
        return $this->with(static function (PdfState $state) use ($filename): void {
            $state->filename = $filename;
        });
    }

    /**
     * Render the document and return the result.
     */
    public function render(): PdfResult
    {
        $startedAt = hrtime(true);

        $config = $this->resolver->resolve($this->state->driver, $this->state->overrides);
        $driver = $this->drivers->get($config->driver);

        $warnings = new WarningCollector;
        $warnings->start();

        try {
            $bytes = RenderGuard::run(
                $config->timeout,
                $config->driver,
                fn (): string => $driver->render($this->state->source, $config),
            );
        } catch (PdfGenerationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw PdfGenerationException::driverFailed($config->driver, $exception->getMessage(), $exception);
        } finally {
            $collected = $warnings->stop();
        }

        $this->assertWithinTimeout($config, $startedAt);

        $processors = $this->processors($config, $driver);

        $context = $this->pipeline->process(new PdfContext($bytes, $config), $processors);

        $this->assertWithinTimeout($config, $startedAt);

        $duration = (hrtime(true) - $startedAt) / 1_000_000;

        $result = new PdfResult(
            $context->bytes,
            $config->driver,
            $this->state->source->type(),
            $duration,
            $this->state->filename,
            strlen($context->bytes),
            $collected,
        );

        $this->log($result);

        if ($config->failOnWarnings && $collected !== []) {
            throw PdfGenerationException::warningsRaised($config->driver, $collected);
        }

        return $result;
    }

    /**
     * The raw bytes of the rendered document.
     */
    public function bytes(): string
    {
        return $this->render()->bytes;
    }

    /**
     * Render the document and save it on disk.
     */
    public function save(string $path): string
    {
        return $this->render()->save($path);
    }

    /**
     * Render the document and stream it in the browser.
     */
    public function stream(): StreamedResponse
    {
        return $this->render()->stream();
    }

    /**
     * Render the document and force the browser to download it.
     */
    public function download(?string $filename = null): Response
    {
        return $this->render()->download($filename);
    }

    /**
     * Render the document and return a regular response.
     */
    public function response(): Response
    {
        return $this->render()->download($this->state->filename);
    }

    /**
     * @return list<Processor>
     */
    private function processors(ResolvedConfig $config, Driver $driver): array
    {
        $processors = [];

        $metadata = $this->state->metadata;

        if ($metadata !== null) {
            $this->assertSupports($driver, Capability::METADATA);
            $processors[] = new SetDocumentMetadata($metadata);
        }

        $packet = $this->packet($config);

        if ($this->state->pdfA) {
            $this->assertSupports($driver, Capability::PDFA);
            $processors[] = new MarkPdfA3b($packet ?? '', $this->state->iccProfile);
        } elseif ($packet !== null) {
            $this->assertSupports($driver, Capability::XMP);
            $processors[] = new EmbedXmpMetadata($packet);
        }

        foreach ($this->state->embeddedFiles as $file) {
            $this->assertSupports($driver, Capability::EMBEDDED_FILES);
            $processors[] = new EmbedFile(
                $file['path'],
                $file['name'],
                $file['relationship'],
                $file['mimeType'],
            );
        }

        if ($this->state->mergePaths !== []) {
            $this->assertSupports($driver, Capability::MERGE);
            $processors[] = new MergePdfs($this->state->mergePaths);
        }

        foreach ($this->state->processors as $processor) {
            $processors[] = $processor;
        }

        $signature = $this->state->signature;

        if ($signature !== null) {
            $this->assertSupports($driver, Capability::SIGNATURE);
            $processors[] = new AddSignature(
                $signature['certificate'],
                $signature['password'],
                $signature['options'],
            );
        }

        return $processors;
    }

    private function assertSupports(Driver $driver, string $capability): void
    {
        if (! $driver->supports($capability)) {
            throw DriverNotSupportedException::missingCapability($driver->name(), $capability);
        }
    }

    private function packet(ResolvedConfig $config): ?string
    {
        $properties = $this->state->xmpProperties;

        if ($this->state->pdfA) {
            $properties = array_merge([
                'pdfaid:part' => '3',
                'pdfaid:conformance' => 'B',
            ], $properties);
        }

        if ($properties === [] && $this->state->metadata === null) {
            return null;
        }

        $metadata = $this->state->metadata ?? new DocumentMetadata;

        $documentId = XmpPacket::documentId($config->driver.'|'.($metadata->title ?? ''));

        return XmpPacket::build($metadata, $properties, $this->state->xmpNamespaces, $documentId);
    }

    private function assertWithinTimeout(ResolvedConfig $config, int $startedAt): void
    {
        $elapsed = (hrtime(true) - $startedAt) / 1_000_000_000;

        if ($elapsed > $config->timeout) {
            throw RenderingTimeoutException::exceeded($config->driver, $config->timeout);
        }
    }

    private function log(PdfResult $result): void
    {
        $this->logger->info('pdf.rendered', [
            'driver' => $result->driver,
            'source' => $result->sourceType,
            'duration_ms' => round($result->durationMs, 2),
            'size' => $result->size(),
        ]);

        foreach ($result->warnings as $warning) {
            $this->logger->warning('pdf.warning', [
                'driver' => $result->driver,
                'source' => $result->sourceType,
                'warning' => $warning,
            ]);
        }
    }

    /**
     * @param  callable(PdfState): void  $mutator
     */
    private function with(callable $mutator): self
    {
        $state = clone $this->state;

        $mutator($state);

        return new self($state, $this->resolver, $this->drivers, $this->pipeline, $this->logger);
    }
}
