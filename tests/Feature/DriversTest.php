<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Drivers\FakeDriver;
use Vellum\Exceptions\DriverNotSupportedException;
use Vellum\Exceptions\SourceNotFoundException;
use Vellum\Tests\TestCase;

final class DriversTest extends TestCase
{
    /**
     * Every shipped driver has to be able to render a document and to give
     * back a valid PDF.
     *
     * @return array<string, list<string>>
     */
    public static function shippedDrivers(): array
    {
        return [
            'dompdf' => ['dompdf'],
            'mpdf' => ['mpdf'],
            'fake' => ['fake'],
        ];
    }

    #[DataProvider('shippedDrivers')]
    public function test_it_renders_a_document_with_every_driver(string $driver): void
    {
        $result = $this->manager()->html('<h1>Hello</h1>')->driver($driver)->render();

        $this->assertSame($driver, $result->driver);
        $this->assertSame('html', $result->sourceType);
        $this->assertStringStartsWith('%PDF-', $result->bytes);
        $this->assertGreaterThan(0, $result->size());
    }

    #[DataProvider('shippedDrivers')]
    public function it_produces_the_same_bytes_for_the_same_document(string $driver): void
    {
        $first = $this->manager()->html('<h1>Hello</h1>')->driver($driver)->render()->bytes;
        $second = $this->manager()->html('<h1>Hello</h1>')->driver($driver)->render()->bytes;

        $this->assertSame($first, $second);
    }

    public function test_it_reports_the_driver_of_the_result(): void
    {
        $result = $this->manager()->html('<p>x</p>')->driver('fake')->render();

        $this->assertSame('fake', $result->driver);
        $this->assertSame('html', $result->sourceType);
    }

    public function test_the_fake_driver_records_the_calls(): void
    {
        $manager = $this->manager();

        $manager->html('<p>x</p>')->driver('fake')->render();

        $calls = $manager->fakeDriver()->calls();

        $this->assertCount(1, $calls);
        $this->assertSame('html', $calls[0]['source']);
        $this->assertSame('fake', $calls[0]['driver']);
    }

    public function test_the_fake_driver_resolves_the_source(): void
    {
        $this->expectException(SourceNotFoundException::class);

        $this->manager()->file('/does/not/exist.html')->driver('fake')->render();
    }

    public function test_it_registers_a_custom_driver(): void
    {
        $manager = $this->manager();

        $manager->register(new class implements Driver
        {
            public function name(): string
            {
                return 'custom';
            }

            /**
             * @return list<string>
             */
            public function capabilities(): array
            {
                return [];
            }

            public function supports(string $capability): bool
            {
                return false;
            }

            public function render(Source $source, ResolvedConfig $config): string
            {
                return '%PDF-1.4 custom';
            }
        });

        $this->assertTrue($manager->hasDriver('custom'));
        $this->assertStringStartsWith('%PDF-', $manager->html('<p>x</p>')->driver('custom')->bytes());
    }

    public function test_it_fails_with_an_unknown_driver(): void
    {
        $this->expectException(DriverNotSupportedException::class);
        $this->expectExceptionMessage('The PDF driver [nope] is not registered.');

        $this->manager()->html('<p>x</p>')->driver('nope')->render();
    }

    public function test_it_fails_when_the_driver_does_not_support_a_capability(): void
    {
        $manager = $this->manager();

        $manager->register(new class implements Driver
        {
            public function name(): string
            {
                return 'limited';
            }

            /**
             * @return list<string>
             */
            public function capabilities(): array
            {
                return [];
            }

            public function supports(string $capability): bool
            {
                return false;
            }

            public function render(Source $source, ResolvedConfig $config): string
            {
                return '%PDF-1.4 limited';
            }
        });

        $this->expectException(DriverNotSupportedException::class);
        $this->expectExceptionMessage('The [limited] driver does not support the [metadata] capability.');

        $manager->html('<p>x</p>')->driver('limited')->metadata(['title' => 'x'])->render();
    }

    public function test_the_browsershot_driver_is_registered(): void
    {
        $this->assertTrue($this->manager()->hasDriver('browsershot'));
    }

    public function test_the_fake_driver_instance_is_shared(): void
    {
        $this->assertInstanceOf(FakeDriver::class, $this->manager()->fakeDriver());
        $this->assertSame($this->manager()->fakeDriver(), $this->manager()->fakeDriver());
    }
}
