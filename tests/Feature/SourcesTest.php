<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Illuminate\View\Factory as ViewFactory;
use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Source;
use Vellum\Exceptions\SourceNotFoundException;
use Vellum\Tests\TestCase;

final class SourcesTest extends TestCase
{
    public function test_it_renders_an_html_source(): void
    {
        $bytes = $this->manager()->html('<h1>Hello</h1>')->driver('fake')->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_renders_a_file_source(): void
    {
        $path = $this->temporaryPath('source.html');

        file_put_contents($path, '<h1>From a file</h1>');

        $result = $this->manager()->file($path)->driver('fake')->render();

        $this->assertSame('file', $result->sourceType);

        @unlink($path);
    }

    public function test_it_fails_with_a_missing_file(): void
    {
        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('could not be found');

        $this->manager()->file('/does/not/exist.html')->render();
    }

    public function test_it_renders_a_blade_view(): void
    {
        $this->application()->make(ViewFactory::class)->addLocation(__DIR__.'/views');

        $bytes = $this->manager()->view('invoice', ['total' => 42])->driver('fake')->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_fails_with_a_missing_view(): void
    {
        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('The view [missing] could not be found.');

        $this->manager()->view('missing')->render();
    }

    public function test_it_renders_a_twig_template(): void
    {
        $directory = sys_get_temp_dir().'/vellum-tests';

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        $path = $directory.'/invoice.twig';

        file_put_contents($path, 'Total: {{ total }}');

        $this->application()->make(Repository::class)->set('pdf.sources.twig.paths', [$directory]);

        $bytes = $this->manager()->twig('invoice', ['total' => 99])->driver('fake')->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);

        @unlink($path);
    }

    public function test_it_fails_with_a_missing_twig_template(): void
    {
        $this->application()->make(Repository::class)->set('pdf.sources.twig.paths', ['/tmp']);

        $this->expectException(SourceNotFoundException::class);

        $this->manager()->twig('does-not-exist')->render();
    }

    public function test_it_rejects_a_url_that_is_not_http(): void
    {
        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('Only http and https URLs are supported.');

        $this->manager()->url('file:///etc/passwd')->render();
    }

    public function test_it_refuses_a_remote_url_when_they_are_disabled(): void
    {
        $this->application()->make(Repository::class)->set('pdf.sources.url.allow_remote', false);

        $this->expectException(SourceNotFoundException::class);
        $this->expectExceptionMessage('Remote sources are disabled by configuration.');

        $this->manager()->url('https://example.com')->render();
    }

    public function test_it_accepts_a_custom_source(): void
    {
        $source = new class implements Source
        {
            public function type(): string
            {
                return 'custom';
            }

            public function describe(): string
            {
                return 'in-memory';
            }

            public function load(ResolvedConfig $config): string
            {
                return '<h1>Custom</h1>';
            }
        };

        $result = $this->manager()->from($source)->driver('fake')->render();

        $this->assertSame('custom', $result->sourceType);
    }
}
