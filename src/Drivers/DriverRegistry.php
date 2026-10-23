<?php

declare(strict_types=1);

namespace Vellum\Drivers;

use Vellum\Contracts\Driver;
use Vellum\Exceptions\DriverNotSupportedException;

/**
 * Holds the available drivers and allows registering custom ones.
 */
final class DriverRegistry
{
    /**
     * @var array<string, Driver>
     */
    private array $drivers = [];

    /**
     * @param  iterable<Driver>  $drivers
     */
    public function __construct(iterable $drivers = [])
    {
        foreach ($drivers as $driver) {
            $this->register($driver);
        }
    }

    public function register(Driver $driver, ?string $name = null): self
    {
        $this->drivers[$name ?? $driver->name()] = $driver;

        return $this;
    }

    /**
     * Register a custom driver, either an instance or a factory.
     *
     * @param  Driver|callable(): Driver  $driver
     */
    public function extend(string $name, Driver|callable $driver): self
    {
        $this->drivers[$name] = $driver instanceof Driver ? $driver : $driver();

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->drivers[$name]);
    }

    public function get(string $name): Driver
    {
        if (! isset($this->drivers[$name])) {
            throw DriverNotSupportedException::unknown($name);
        }

        return $this->drivers[$name];
    }

    /**
     * @return array<string, Driver>
     */
    public function all(): array
    {
        return $this->drivers;
    }
}
