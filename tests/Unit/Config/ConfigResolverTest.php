<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Vellum\Config\ConfigResolver;
use Vellum\Config\DocumentOptions;
use Vellum\Config\Margins;
use Vellum\Config\PdfConfig;
use Vellum\Exceptions\InvalidConfigurationException;

final class ConfigResolverTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function resolver(array $config): ConfigResolver
    {
        return new ConfigResolver(PdfConfig::fromArray($config));
    }

    public function test_it_uses_the_default_driver(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'mpdf',
            'drivers' => ['mpdf' => ['options' => []]],
        ]);

        $this->assertSame('mpdf', $resolver->resolve()->driver);
    }

    public function test_the_driver_defaults_win_over_the_global_defaults(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'defaults' => ['paper' => 'a4', 'dpi' => 96],
            'drivers' => ['dompdf' => ['paper' => 'a5', 'dpi' => 300]],
        ]);

        $resolved = $resolver->resolve();

        $this->assertSame('a5', $resolved->paper->value);
        $this->assertSame(300, $resolved->dpi);
    }

    public function test_the_document_overrides_win_over_the_driver_defaults(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'defaults' => ['paper' => 'a4'],
            'drivers' => ['dompdf' => ['paper' => 'a5']],
        ]);

        $resolved = $resolver->resolve('dompdf', new DocumentOptions(paper: 'letter'));

        $this->assertSame('letter', $resolved->paper->value);
    }

    public function test_the_driver_options_and_the_document_options_are_kept_apart(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'drivers' => ['dompdf' => ['options' => ['isRemoteEnabled' => false, 'scale' => 1]]],
        ]);

        $resolved = $resolver->resolve('dompdf', (new DocumentOptions)->withOption('isRemoteEnabled', true));

        // The options of the document win over the ones of the config file.
        $this->assertTrue($resolved->options['isRemoteEnabled']);
        $this->assertSame(1, $resolved->options['scale']);

        // The options of the document are kept apart, so the drivers can
        // apply them after the settings derived from the document.
        $this->assertSame(['isRemoteEnabled' => true], $resolved->documentOptions);
    }

    public function test_the_document_options_are_empty_when_the_document_does_not_set_any(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'drivers' => ['dompdf' => ['options' => ['isRemoteEnabled' => false]]],
        ]);

        $resolved = $resolver->resolve('dompdf');

        $this->assertSame([], $resolved->documentOptions);
        $this->assertFalse($resolved->options['isRemoteEnabled']);
    }

    public function test_it_merges_the_margins(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'defaults' => ['margins' => ['top' => 10.0, 'right' => 10.0, 'bottom' => 10.0, 'left' => 10.0]],
        ]);

        $resolved = $resolver->resolve();

        $this->assertInstanceOf(Margins::class, $resolved->margins);
        $this->assertSame(10.0, $resolved->margins->top);
    }

    public function test_it_fails_when_the_driver_name_is_empty(): void
    {
        $resolver = $this->resolver(['default_driver' => '']);

        $this->expectException(InvalidConfigurationException::class);

        $resolver->resolve();
    }

    public function test_it_fails_with_an_unsupported_paper_size(): void
    {
        $resolver = $this->resolver(['default_driver' => 'dompdf']);

        $this->expectException(InvalidConfigurationException::class);

        $resolver->resolve('dompdf', new DocumentOptions(paper: 'nope'));
    }

    public function test_it_exposes_the_source_configuration(): void
    {
        $resolver = $this->resolver([
            'default_driver' => 'dompdf',
            'sources' => [
                'twig' => ['paths' => ['/tmp/views'], 'extension' => 'twig'],
                'url' => ['allow_remote' => false, 'timeout' => 5],
            ],
        ]);

        $sources = $resolver->resolve()->sources();

        $this->assertSame(['/tmp/views'], $sources->twigPaths);
        $this->assertSame('twig', $sources->twigExtension);
        $this->assertFalse($sources->allowRemote);
        $this->assertSame(5, $sources->urlTimeout);
    }

    public function test_the_document_options_are_immutable(): void
    {
        $options = new DocumentOptions(paper: 'a4');

        $updated = $options->withPaper('a5');

        $this->assertSame('a4', $options->paper);
        $this->assertSame('a5', $updated->paper);
        $this->assertNotSame($options, $updated);
    }

    public function test_the_document_options_merge_keeps_the_empty_values(): void
    {
        $base = new DocumentOptions(paper: 'a4', dpi: 96, options: ['a' => 1]);
        $merged = $base->merge(new DocumentOptions(dpi: 300, options: ['b' => 2]));

        $this->assertSame('a4', $merged->paper);
        $this->assertSame(300, $merged->dpi);
        $this->assertSame(['a' => 1, 'b' => 2], $merged->options);
    }
}
