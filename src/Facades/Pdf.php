<?php

declare(strict_types=1);

namespace Vellum\Facades;

use Illuminate\Support\Facades\Facade;
use Vellum\PdfManager;

/**
 * @method static \Vellum\PdfBuilder from(\Vellum\Contracts\Source $source)
 * @method static \Vellum\PdfBuilder html(string $html)
 * @method static \Vellum\PdfBuilder view(string $view, array<string, mixed> $data = [])
 * @method static \Vellum\PdfBuilder file(string $path)
 * @method static \Vellum\PdfBuilder url(string $url)
 * @method static \Vellum\PdfBuilder twig(string $template, array<string, mixed> $data = [])
 * @method static \Vellum\PdfBuilder fake()
 * @method static \Vellum\PdfManager register(\Vellum\Contracts\Driver $driver, ?string $name = null)
 * @method static \Vellum\PdfManager extend(string $name, \Vellum\Contracts\Driver|callable(): \Vellum\Contracts\Driver $driver)
 * @method static bool hasDriver(string $name)
 * @method static \Vellum\Contracts\Driver driver(string $name)
 * @method static \Vellum\Drivers\FakeDriver fakeDriver()
 *
 * @see PdfManager
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfManager::class;
    }
}
