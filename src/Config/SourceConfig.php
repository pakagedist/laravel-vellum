<?php

declare(strict_types=1);

namespace Vellum\Config;

/**
 * Settings for the built-in source types.
 */
final class SourceConfig
{
    /**
     * @param  list<string>  $twigPaths
     * @param  array<string, string>  $urlHeaders
     */
    public function __construct(
        public readonly array $twigPaths = [],
        public readonly string $twigExtension = 'twig',
        public readonly bool $allowRemote = true,
        public readonly int $urlTimeout = 15,
        public readonly array $urlHeaders = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $twig = ConfigValue::map($config, 'twig');
        $url = ConfigValue::map($config, 'url');

        return new self(
            twigPaths: ConfigValue::stringList($twig, 'paths'),
            twigExtension: ConfigValue::string($twig, 'extension', 'twig') ?? 'twig',
            allowRemote: ConfigValue::boolean($url, 'allow_remote', true) ?? true,
            urlTimeout: ConfigValue::integer($url, 'timeout', 15) ?? 15,
            urlHeaders: ConfigValue::stringMap($url, 'headers'),
        );
    }
}
