<?php

declare(strict_types=1);

namespace Vellum\Config;

use Vellum\Exceptions\InvalidConfigurationException;

/**
 * Resolves the layered configuration into a single immutable object.
 *
 * Layering order: global defaults < driver defaults < document overrides.
 */
final class ConfigResolver
{
    public function __construct(
        private readonly PdfConfig $config,
    ) {}

    public function config(): PdfConfig
    {
        return $this->config;
    }

    public function resolve(?string $driver = null, ?DocumentOptions $overrides = null): ResolvedConfig
    {
        $driverName = $driver ?? $this->config->defaultDriver;

        if ($driverName === '') {
            throw InvalidConfigurationException::missingDriver($driverName);
        }

        $driverConfig = $this->config->driver($driverName);

        $overrides ??= new DocumentOptions;

        $driverOptions = $driverConfig instanceof DriverConfig ? $driverConfig->options : new DocumentOptions;

        $merged = $this->config->defaults
            ->merge($driverOptions)
            ->merge($overrides);

        return new ResolvedConfig(
            driver: $driverName,
            paper: PaperSize::parse((string) ($merged->paper ?? PaperSize::A4->value)),
            orientation: Orientation::parse((string) ($merged->orientation ?? 'portrait')),
            font: $merged->font,
            fontFile: $merged->fontFile,
            dpi: $merged->dpi ?? 96,
            margins: $merged->margins ?? new Margins,
            tempFolder: $merged->tempFolder,
            fontCachePath: $merged->fontCachePath,
            allowRemoteAssets: $merged->allowRemoteAssets ?? false,
            timeout: $merged->timeout ?? 30,
            failOnWarnings: $merged->failOnWarnings ?? false,
            options: $merged->options,
            documentOptions: $overrides->options,
            sources: $this->config->sources,
        );
    }
}
