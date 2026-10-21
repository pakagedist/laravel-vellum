<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vellum\Config\Margins;
use Vellum\Config\Orientation;
use Vellum\Config\PaperSize;
use Vellum\Config\ResolvedConfig;
use Vellum\Config\SourceConfig;
use Vellum\Contracts\Processor;
use Vellum\Exceptions\PdfGenerationException;
use Vellum\Pipeline\PdfContext;
use Vellum\Pipeline\Pipeline;

final class PipelineTest extends TestCase
{
    public function test_it_runs_the_processors_in_order(): void
    {
        $pipeline = new Pipeline;

        $pipeline->add($this->processor('first', 'a'));
        $pipeline->add($this->processor('second', 'b'));

        $context = $pipeline->process($this->context());

        $this->assertSame('ab', $context->attribute('applied'));
    }

    public function test_it_runs_the_global_processors_before_the_document_ones(): void
    {
        $pipeline = new Pipeline([$this->processor('global', 'g')]);

        $context = $pipeline->process($this->context(), [$this->processor('document', 'd')]);

        $this->assertSame('gd', $context->attribute('applied'));
    }

    public function test_it_returns_the_same_context_when_there_is_no_processor(): void
    {
        $pipeline = new Pipeline;

        $context = $this->context();

        $this->assertSame($context, $pipeline->process($context));
    }

    public function test_it_wraps_the_failures_of_a_processor(): void
    {
        $pipeline = new Pipeline;

        $pipeline->add(new class implements Processor
        {
            public function name(): string
            {
                return 'broken';
            }

            public function process(PdfContext $context): PdfContext
            {
                throw new RuntimeException('boom');
            }
        });

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('The [broken] post processor failed: boom');

        $pipeline->process($this->context());
    }

    public function test_it_does_not_wrap_the_package_exceptions(): void
    {
        $pipeline = new Pipeline;

        $pipeline->add(new class implements Processor
        {
            public function name(): string
            {
                return 'broken';
            }

            public function process(PdfContext $context): PdfContext
            {
                throw PdfGenerationException::driverFailed('dompdf', 'nope');
            }
        });

        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('The [dompdf] driver failed to render the PDF: nope');

        $pipeline->process($this->context());
    }

    public function test_the_context_keeps_the_attributes(): void
    {
        $context = $this->context()->withAttribute('key', 'value');

        $this->assertSame('value', $context->attribute('key'));
        $this->assertNull($context->attribute('missing'));
        $this->assertSame('fallback', $context->attribute('missing', 'fallback'));
    }

    public function test_the_context_keeps_the_bytes(): void
    {
        $context = $this->context()->withBytes('other');

        $this->assertSame('other', $context->bytes);
        $this->assertSame('bytes', $this->context()->bytes);
    }

    public function test_the_resolved_config_exposes_the_sources(): void
    {
        $config = new ResolvedConfig(
            'fake',
            PaperSize::A4,
            Orientation::Portrait,
            null,
            null,
            96,
            new Margins,
            null,
            null,
            false,
            30,
        );

        $this->assertInstanceOf(SourceConfig::class, $config->sources());
    }

    private function context(): PdfContext
    {
        return new PdfContext('bytes', new ResolvedConfig(
            'fake',
            PaperSize::A4,
            Orientation::Portrait,
            null,
            null,
            96,
            new Margins,
            null,
            null,
            false,
            30,
        ));
    }

    private function processor(string $name, string $value): Processor
    {
        return new class($name, $value) implements Processor
        {
            public function __construct(
                private readonly string $name,
                private readonly string $value,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function process(PdfContext $context): PdfContext
            {
                $applied = $context->attribute('applied');

                return $context->withAttribute('applied', (is_string($applied) ? $applied : '').$this->value);
            }
        };
    }
}
