<?php

declare(strict_types=1);

namespace Vellum;

use Psr\Log\LoggerInterface;
use Vellum\Config\ConfigResolver;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Drivers\DriverRegistry;
use Vellum\Drivers\FakeDriver;
use Vellum\Pipeline\Pipeline;
use Vellum\Sources\BladeSource;
use Vellum\Sources\FileSource;
use Vellum\Sources\HtmlSource;
use Vellum\Sources\TwigSource;
use Vellum\Sources\UrlSource;

/**
 * The entry point of the package, resolvable from the container.
 */
final class PdfManager
{
    public function __construct(
        private readonly ConfigResolver $config,
        private readonly DriverRegistry $drivers,
        private readonly Pipeline $pipeline,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Start a document from any source.
     */
    public function from(Source $source): PdfBuilder
    {
        return new PdfBuilder(
            new PdfState($source),
            $this->config,
            $this->drivers,
            $this->pipeline,
            $this->logger,
        );
    }

    public function html(string $html): PdfBuilder
    {
        return $this->from(HtmlSource::make($html));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function view(string $view, array $data = []): PdfBuilder
    {
        return $this->from(BladeSource::make($view, $data));
    }

    public function file(string $path): PdfBuilder
    {
        return $this->from(FileSource::make($path));
    }

    public function url(string $url): PdfBuilder
    {
        return $this->from(UrlSource::make($url));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function twig(string $template, array $data = []): PdfBuilder
    {
        return $this->from(TwigSource::make($template, $data));
    }

    /**
     * Start a document rendered by the fake driver.
     */
    public function fake(): PdfBuilder
    {
        return $this->from(HtmlSource::make('Fake document'))->driver('fake');
    }

    /**
     * Register a custom driver.
     */
    public function register(Driver $driver, ?string $name = null): self
    {
        $this->drivers->register($driver, $name);

        return $this;
    }

    /**
     * Register a custom driver under a given name.
     *
     * @param  Driver|callable(): Driver  $driver
     */
    public function extend(string $name, Driver|callable $driver): self
    {
        $this->drivers->extend($name, $driver);

        return $this;
    }

    public function hasDriver(string $name): bool
    {
        return $this->drivers->has($name);
    }

    public function driver(string $name): Driver
    {
        return $this->drivers->get($name);
    }

    /**
     * The fake driver instance, useful to assert the calls in tests.
     */
    public function fakeDriver(): FakeDriver
    {
        if (! $this->drivers->has('fake')) {
            $this->drivers->register(new FakeDriver);
        }

        $driver = $this->drivers->get('fake');

        if (! $driver instanceof FakeDriver) {
            throw new \LogicException('The [fake] driver is already registered with another implementation.');
        }

        return $driver;
    }
}
