<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Vellum\Config\Margins;
use Vellum\Config\Orientation;
use Vellum\Config\PaperSize;
use Vellum\Exceptions\InvalidConfigurationException;

final class PaperSizeTest extends TestCase
{
    public function test_it_exposes_the_common_paper_sizes(): void
    {
        $this->assertSame('a4', PaperSize::A4->value);
        $this->assertSame('letter', PaperSize::Letter->value);
        $this->assertSame('legal', PaperSize::Legal->value);
    }

    public function test_it_parses_a_paper_size(): void
    {
        $this->assertSame(PaperSize::A4, PaperSize::parse('a4'));
        $this->assertSame(PaperSize::A5, PaperSize::parse('A5'));
        $this->assertSame(PaperSize::Legal, PaperSize::parse('legal'));
    }

    public function test_it_parses_a_paper_size_with_a_label(): void
    {
        $this->assertSame(PaperSize::A4, PaperSize::parse(PaperSize::A4->label()));
    }

    public function test_it_throws_a_package_exception_for_an_unknown_size(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unsupported paper size [nope].');

        PaperSize::parse('nope');
    }

    public function test_it_parses_the_orientation(): void
    {
        $this->assertSame(Orientation::Portrait, Orientation::parse('portrait'));
        $this->assertSame(Orientation::Landscape, Orientation::parse('landscape'));
        $this->assertTrue(Orientation::parse('landscape')->isLandscape());
        $this->assertFalse(Orientation::parse('portrait')->isLandscape());
    }

    public function test_it_throws_a_package_exception_for_an_unknown_orientation(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unsupported orientation [sideways].');

        Orientation::parse('sideways');
    }

    public function test_it_builds_the_margins_from_an_array(): void
    {
        $margins = Margins::fromArray([
            'top' => 1.0,
            'right' => 2.0,
            'bottom' => 3.0,
            'left' => 4.0,
        ]);

        $this->assertSame(1.0, $margins->top);
        $this->assertSame(2.0, $margins->right);
        $this->assertSame(3.0, $margins->bottom);
        $this->assertSame(4.0, $margins->left);
        $this->assertFalse($margins->isEmpty());
    }

    public function test_it_builds_the_default_margins(): void
    {
        $margins = Margins::fromArray([]);

        $this->assertSame(0.0, $margins->top);
        $this->assertTrue($margins->isEmpty());
    }
}
