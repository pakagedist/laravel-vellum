<?php

declare(strict_types=1);

namespace Vellum\Config;

use Vellum\Exceptions\InvalidConfigurationException;

/**
 * The root configuration object, built from the published config file.
 */
final class PdfConfig
{
    /**
     * @param  array<string, DriverConfig>  $drivers
     */
    public function __construct(
        public readonly string $defaultDriver,
        public readonly DocumentOptions $defaults,
        public readonly array $drivers,
        public readonly SourceConfig $sources,
        public readonly ?string $logChannel,
        public readonly string $logLevel,
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $drivers = [];

        foreach (ConfigValue::map($config, 'drivers') as $name => $driverConfig) {
            if (! is_array($driverConfig)) {
                throw InvalidConfigurationException::invalidType('drivers.'.$name, 'an array', $driverConfig);
            }

            $drivers[$name] = DriverConfig::fromArray($name, $driverConfig);
        }

        $logging = ConfigValue::map($config, 'logging');
        $channel = ConfigValue::string($logging, 'channel');

        return new self(
            defaultDriver: ConfigValue::string($config, 'default_driver', 'dompdf') ?? 'dompdf',
            defaults: DocumentOptions::fromArray(ConfigValue::map($config, 'defaults')),
            drivers: $drivers,
            sources: SourceConfig::fromArray(ConfigValue::map($config, 'sources')),
            logChannel: $channel === null || $channel === '' ? null : $channel,
            logLevel: ConfigValue::string($logging, 'level', 'info') ?? 'info',
        );
    }

    public function driver(string $name): ?DriverConfig
    {
        return $this->drivers[$name] ?? null;
    }
}
