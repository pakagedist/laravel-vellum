<?php

declare(strict_types=1);

namespace Vellum\Config;

use Vellum\Exceptions\InvalidConfigurationException;

/**
 * Typed readers for the values coming from the configuration files.
 *
 * Every reader validates the type of the value and fails with an
 * InvalidConfigurationException when the configuration file is not usable.
 */
final class ConfigValue
{
    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function string(array $config, string $key, ?string $default = null): ?string
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvalidConfigurationException::invalidType($key, 'a string', $value);
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function integer(array $config, string $key, ?int $default = null): ?int
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        throw InvalidConfigurationException::invalidType($key, 'an integer', $value);
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function boolean(array $config, string $key, ?bool $default = null): ?bool
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off', '' => false,
                default => throw InvalidConfigurationException::invalidType($key, 'a boolean', $value),
            };
        }

        throw InvalidConfigurationException::invalidType($key, 'a boolean', $value);
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<array-key, mixed>
     */
    public static function array(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            throw InvalidConfigurationException::invalidType($key, 'an array', $value);
        }

        return $value;
    }

    /**
     * A map of values, the keys are always strings.
     *
     * @param  array<array-key, mixed>  $config
     * @return array<string, mixed>
     */
    public static function map(array $config, string $key): array
    {
        $values = [];

        foreach (self::array($config, $key) as $name => $value) {
            $values[(string) $name] = $value;
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return list<string>
     */
    public static function stringList(array $config, string $key): array
    {
        $values = [];

        foreach (self::array($config, $key) as $value) {
            if (! is_string($value)) {
                throw InvalidConfigurationException::invalidType($key, 'a list of strings', $value);
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<string, string>
     */
    public static function stringMap(array $config, string $key): array
    {
        $values = [];

        foreach (self::array($config, $key) as $name => $value) {
            if (! is_string($value)) {
                throw InvalidConfigurationException::invalidType($key, 'a map of strings', $value);
            }

            $values[(string) $name] = $value;
        }

        return $values;
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<string, float|int|string>
     */
    public static function numberMap(array $config, string $key): array
    {
        $values = [];

        foreach (self::array($config, $key) as $name => $value) {
            if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
                throw InvalidConfigurationException::invalidType($key, 'a map of numbers', $value);
            }

            $values[(string) $name] = $value;
        }

        return $values;
    }
}
