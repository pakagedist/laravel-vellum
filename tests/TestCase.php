<?php

declare(strict_types=1);

namespace Vellum\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use RuntimeException;
use Vellum\Facades\Pdf;
use Vellum\PdfManager;
use Vellum\PdfServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PdfServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Pdf' => Pdf::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('pdf.default_driver', 'fake');
    }

    protected function manager(): PdfManager
    {
        return $this->application()->make(PdfManager::class);
    }

    /**
     * The application of the test, it is always available.
     */
    protected function application(): Application
    {
        $app = $this->app;

        if (! $app instanceof Application) {
            throw new RuntimeException('The application of the test is not available.');
        }

        return $app;
    }

    /**
     * A minimal, valid PDF file used as a fixture.
     */
    protected function pdfFixture(): string
    {
        $path = $this->temporaryPath('fixture.pdf');

        file_put_contents($path, $this->manager()->html('<p>fixture</p>')->driver('dompdf')->bytes());

        return $path;
    }

    protected function temporaryPath(string $name): string
    {
        $directory = sys_get_temp_dir().'/vellum-tests';

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        return $directory.'/'.uniqid('test-', true).'-'.$name;
    }
}
