<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Illuminate\Contracts\Config\Repository;
use Vellum\Config\ConfigResolver;
use Vellum\Config\DocumentOptions;
use Vellum\Contracts\Processor;
use Vellum\Exceptions\InvalidConfigurationException;
use Vellum\Pipeline\PdfContext;
use Vellum\Tests\TestCase;

final class ConfigurationTest extends TestCase
{
    public function test_the_global_defaults_are_applied(): void
    {
        $config = $this->resolver()->resolve('fake');

        $this->assertSame('a4', $config->paper->value);
        $this->assertSame('portrait', $config->orientation->value);
        $this->assertSame(96, $config->dpi);
        $this->assertFalse($config->allowRemoteAssets);
    }

    public function test_the_driver_defaults_win_over_the_global_defaults(): void
    {
        $this->config()->set('pdf.drivers.fake.dpi', 150);

        $config = $this->resolver()->resolve('fake');

        $this->assertSame(150, $config->dpi);
    }

    public function test_the_document_options_win_over_the_driver_defaults(): void
    {
        $this->config()->set('pdf.drivers.fake.dpi', 150);

        $config = $this->resolver()->resolve('fake', (new DocumentOptions)->withDpi(300));

        $this->assertSame(300, $config->dpi);
    }

    public function test_the_engine_options_are_forwarded_to_the_driver(): void
    {
        $this->config()->set('pdf.drivers.fake.options.scale', 2);

        $config = $this->resolver()->resolve('fake');

        $this->assertSame(2, $config->options['scale']);
    }

    public function test_it_fails_with_an_invalid_configuration_value(): void
    {
        $this->config()->set('pdf.defaults.dpi', 'not a number');

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The configuration value [dpi] must be an integer');

        $this->manager()->html('<p>x</p>')->driver('fake')->render();
    }

    public function test_it_fails_with_an_invalid_paper_size(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->manager()->html('<p>x</p>')->paper('A0')->driver('fake')->render();
    }

    public function test_the_global_processors_of_the_config_file_are_resolved_from_the_container(): void
    {
        $this->application()->instance(GlobalProcessor::class, new GlobalProcessor);

        $this->config()->set('pdf.processors', [GlobalProcessor::class]);

        $result = $this->manager()->html('<p>x</p>')->driver('fake')->render();

        $this->assertStringContainsString('Global proc', $result->bytes);
    }

    public function test_the_source_configuration_is_exposed(): void
    {
        $this->config()->set('pdf.sources.twig.extension', 'html.twig');
        $this->config()->set('pdf.sources.url.timeout', 42);

        $config = $this->resolver()->resolve('fake');

        $this->assertSame('html.twig', $config->sources()->twigExtension);
        $this->assertSame(42, $config->sources()->urlTimeout);
    }

    private function config(): Repository
    {
        return $this->application()->make(Repository::class);
    }

    private function resolver(): ConfigResolver
    {
        return $this->application()->make(ConfigResolver::class);
    }
}

/**
 * A processor registered globally through the configuration file.
 */
final class GlobalProcessor implements Processor
{
    public function name(): string
    {
        return 'global';
    }

    public function process(PdfContext $context): PdfContext
    {
        // The replacement keeps the length of the document, so the cross
        // reference table stays valid.
        return $context->withBytes(str_replace('Vellum', 'Global proc', $context->bytes));
    }
}
