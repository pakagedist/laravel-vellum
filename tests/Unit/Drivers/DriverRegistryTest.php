<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Drivers;

use PHPUnit\Framework\TestCase;
use Vellum\Contracts\Capability;
use Vellum\Contracts\Driver;
use Vellum\Drivers\DriverRegistry;
use Vellum\Drivers\FakeDriver;
use Vellum\Exceptions\DriverNotSupportedException;

final class DriverRegistryTest extends TestCase
{
    public function test_it_registers_a_driver(): void
    {
        $registry = new DriverRegistry([new FakeDriver]);

        $this->assertTrue($registry->has('fake'));
        $this->assertInstanceOf(FakeDriver::class, $registry->get('fake'));
    }

    public function test_it_registers_a_driver_with_a_custom_name(): void
    {
        $registry = new DriverRegistry;

        $registry->register(new FakeDriver, 'stub');

        $this->assertTrue($registry->has('stub'));
    }

    public function test_it_extends_the_registry_with_a_callable(): void
    {
        $registry = new DriverRegistry;

        $registry->extend('lazy', static fn (): Driver => new FakeDriver);

        $this->assertTrue($registry->has('lazy'));
        $this->assertInstanceOf(FakeDriver::class, $registry->get('lazy'));
    }

    public function test_it_fails_with_an_unknown_driver(): void
    {
        $registry = new DriverRegistry;

        $this->expectException(DriverNotSupportedException::class);
        $this->expectExceptionMessage('The PDF driver [nope] is not registered.');

        $registry->get('nope');
    }

    public function test_the_fake_driver_declares_every_capability(): void
    {
        $driver = new FakeDriver;

        foreach (Capability::all() as $capability) {
            $this->assertTrue($driver->supports($capability));
        }
    }
}
