<?php

declare(strict_types=1);

namespace Vellum\Config;

/**
 * The leaf configuration: every value is nullable so a layer can leave a
 * value untouched and let the next layer decide.
 */
final class DocumentOptions
{
    /**
     * @param  array<string, mixed>  $options  Engine specific options.
     */
    public function __construct(
        public readonly ?string $paper = null,
        public readonly ?string $orientation = null,
        public readonly ?string $font = null,
        public readonly ?string $fontFile = null,
        public readonly ?int $dpi = null,
        public readonly ?Margins $margins = null,
        public readonly ?string $tempFolder = null,
        public readonly ?string $fontCachePath = null,
        public readonly ?bool $allowRemoteAssets = null,
        public readonly ?int $timeout = null,
        public readonly ?bool $failOnWarnings = null,
        public readonly array $options = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $margins = ConfigValue::numberMap($config, 'margins');

        return new self(
            paper: ConfigValue::string($config, 'paper'),
            orientation: ConfigValue::string($config, 'orientation'),
            font: ConfigValue::string($config, 'font'),
            fontFile: ConfigValue::string($config, 'font_file'),
            dpi: ConfigValue::integer($config, 'dpi'),
            margins: $margins === [] ? null : Margins::fromArray($margins),
            tempFolder: ConfigValue::string($config, 'temp_folder'),
            fontCachePath: ConfigValue::string($config, 'font_cache_path'),
            allowRemoteAssets: ConfigValue::boolean($config, 'allow_remote_assets'),
            timeout: ConfigValue::integer($config, 'timeout'),
            failOnWarnings: ConfigValue::boolean($config, 'fail_on_warnings'),
            options: ConfigValue::map($config, 'options'),
        );
    }

    /**
     * Merge another layer on top of this one. Non null values win.
     */
    public function merge(self $overrides): self
    {
        return new self(
            paper: $overrides->paper ?? $this->paper,
            orientation: $overrides->orientation ?? $this->orientation,
            font: $overrides->font ?? $this->font,
            fontFile: $overrides->fontFile ?? $this->fontFile,
            dpi: $overrides->dpi ?? $this->dpi,
            margins: $overrides->margins ?? $this->margins,
            tempFolder: $overrides->tempFolder ?? $this->tempFolder,
            fontCachePath: $overrides->fontCachePath ?? $this->fontCachePath,
            allowRemoteAssets: $overrides->allowRemoteAssets ?? $this->allowRemoteAssets,
            timeout: $overrides->timeout ?? $this->timeout,
            failOnWarnings: $overrides->failOnWarnings ?? $this->failOnWarnings,
            options: array_merge($this->options, $overrides->options),
        );
    }

    /**
     * Return a copy with a single engine option added.
     */
    public function withOption(string $key, mixed $value): self
    {
        return new self(
            paper: $this->paper,
            orientation: $this->orientation,
            font: $this->font,
            fontFile: $this->fontFile,
            dpi: $this->dpi,
            margins: $this->margins,
            tempFolder: $this->tempFolder,
            fontCachePath: $this->fontCachePath,
            allowRemoteAssets: $this->allowRemoteAssets,
            timeout: $this->timeout,
            failOnWarnings: $this->failOnWarnings,
            options: array_merge($this->options, [$key => $value]),
        );
    }

    public function withPaper(string $paper): self
    {
        return $this->copy(paper: $paper);
    }

    public function withOrientation(string $orientation): self
    {
        return $this->copy(orientation: $orientation);
    }

    public function withFont(?string $font, ?string $file = null): self
    {
        return $this->copy(font: $font, fontFile: $file);
    }

    public function withDpi(int $dpi): self
    {
        return $this->copy(dpi: $dpi);
    }

    public function withMargins(Margins $margins): self
    {
        return $this->copy(margins: $margins);
    }

    public function withTempFolder(string $path): self
    {
        return $this->copy(tempFolder: $path);
    }

    public function withFontCachePath(string $path): self
    {
        return $this->copy(fontCachePath: $path);
    }

    public function withAllowRemoteAssets(bool $allowed): self
    {
        return $this->copy(allowRemoteAssets: $allowed);
    }

    public function withTimeout(int $seconds): self
    {
        return $this->copy(timeout: $seconds);
    }

    public function withFailOnWarnings(bool $fail): self
    {
        return $this->copy(failOnWarnings: $fail);
    }

    private function copy(
        ?string $paper = null,
        ?string $orientation = null,
        ?string $font = null,
        ?string $fontFile = null,
        ?int $dpi = null,
        ?Margins $margins = null,
        ?string $tempFolder = null,
        ?string $fontCachePath = null,
        ?bool $allowRemoteAssets = null,
        ?int $timeout = null,
        ?bool $failOnWarnings = null,
    ): self {
        return new self(
            paper: $paper ?? $this->paper,
            orientation: $orientation ?? $this->orientation,
            font: $font ?? $this->font,
            fontFile: $fontFile ?? $this->fontFile,
            dpi: $dpi ?? $this->dpi,
            margins: $margins ?? $this->margins,
            tempFolder: $tempFolder ?? $this->tempFolder,
            fontCachePath: $fontCachePath ?? $this->fontCachePath,
            allowRemoteAssets: $allowRemoteAssets ?? $this->allowRemoteAssets,
            timeout: $timeout ?? $this->timeout,
            failOnWarnings: $failOnWarnings ?? $this->failOnWarnings,
            options: $this->options,
        );
    }
}
