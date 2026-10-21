<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Vellum\Config\ResolvedConfig;
use Vellum\Contracts\Driver;
use Vellum\Contracts\Source;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Exceptions\RenderingTimeoutException;
use Vellum\Tests\TestCase;

final class ResilienceTest extends TestCase
{
    public function test_it_wraps_the_failures_of_the_driver(): void
    {
        $this->manager()->register(new BrokenDriver);

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('The [broken] driver failed to render the PDF: the engine exploded');

        $this->manager()->html('<p>x</p>')->driver('broken')->render();
    }

    public function test_it_collects_the_warnings_of_the_engine(): void
    {
        $this->manager()->register(new WarningDriver);

        $result = $this->manager()->html('<p>x</p>')->driver('noisy')->render();

        $this->assertNotSame([], $result->warnings);
        $this->assertStringContainsString('the engine is noisy', $result->warnings[0]);
    }

    public function test_it_turns_the_warnings_into_an_exception_when_they_are_strict(): void
    {
        $this->manager()->register(new WarningDriver);

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('The [noisy] driver raised 1 warning(s)');

        $this->manager()->html('<p>x</p>')->driver('noisy')->strictWarnings()->render();
    }

    public function test_it_aborts_a_render_that_takes_too_long(): void
    {
        $this->manager()->register(new SlowDriver);

        $this->expectException(RenderingTimeoutException::class);
        $this->expectExceptionMessage('The [slow] driver exceeded the configured timeout of 1 seconds.');

        $this->manager()->html('<p>x</p>')->driver('slow')->timeout(1)->render();
    }
}

/**
 * A driver that always fails.
 */
final class BrokenDriver implements Driver
{
    public function name(): string
    {
        return 'broken';
    }

    public function render(Source $source, ResolvedConfig $config): string
    {
        throw new \RuntimeException('the engine exploded');
    }

    public function capabilities(): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return false;
    }
}

/**
 * A driver that raises a warning while rendering.
 */
final class WarningDriver implements Driver
{
    public function name(): string
    {
        return 'noisy';
    }

    public function render(Source $source, ResolvedConfig $config): string
    {
        trigger_error('the engine is noisy', E_USER_WARNING);

        return "%PDF-1.7\n";
    }

    public function capabilities(): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return false;
    }
}

/**
 * A driver that never finishes on time.
 */
final class SlowDriver implements Driver
{
    public function name(): string
    {
        return 'slow';
    }

    public function render(Source $source, ResolvedConfig $config): string
    {
        sleep(5);

        return "%PDF-1.7\n";
    }

    public function capabilities(): array
    {
        return [];
    }

    public function supports(string $capability): bool
    {
        return false;
    }
}
