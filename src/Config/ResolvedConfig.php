<?php

declare(strict_types=1);

namespace Vellum\Config;

/**
 * The final, fully resolved configuration used to render a document.
 */
final class ResolvedConfig
{
    /**
     * @param  array<string, mixed>  $options  Engine options coming from the config file.
     * @param  array<string, mixed>  $documentOptions  Engine options set on the document.
     */
    public function __construct(
        public readonly string $driver,
        public readonly PaperSize $paper,
        public readonly Orientation $orientation,
        public readonly ?string $font,
        public readonly ?string $fontFile,
        public readonly int $dpi,
        public readonly Margins $margins,
        public readonly ?string $tempFolder,
        public readonly ?string $fontCachePath,
        public readonly bool $allowRemoteAssets,
        public readonly int $timeout,
        public readonly bool $failOnWarnings = false,
        public readonly array $options = [],
        public readonly array $documentOptions = [],
        public readonly ?SourceConfig $sources = null,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): self
    {
        return new self(
            $this->driver,
            $this->paper,
            $this->orientation,
            $this->font,
            $this->fontFile,
            $this->dpi,
            $this->margins,
            $this->tempFolder,
            $this->fontCachePath,
            $this->allowRemoteAssets,
            $this->timeout,
            $this->failOnWarnings,
            array_merge($this->options, $options),
            $this->documentOptions,
            $this->sources,
        );
    }

    /**
     * The settings of the source types.
     */
    public function sources(): SourceConfig
    {
        return $this->sources ?? new SourceConfig;
    }
}
