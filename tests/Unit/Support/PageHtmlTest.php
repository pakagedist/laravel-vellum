<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Vellum\Config\Margins;
use Vellum\Config\Orientation;
use Vellum\Config\PaperSize;
use Vellum\Support\PageHtml;

final class PageHtmlTest extends TestCase
{
    public function test_it_injects_the_page_rule_in_the_head(): void
    {
        $html = PageHtml::injectMargins(
            '<html><head><title>x</title></head><body>content</body></html>',
            new Margins(10.0, 10.0, 10.0, 10.0),
        );

        $this->assertStringContainsString('@page{margin:10mm 10mm 10mm 10mm;}', $html);
        $this->assertLessThan(
            strpos($html, '</head>'),
            (int) strpos($html, '<style>'),
        );
    }

    public function test_it_does_not_inject_the_page_rule_when_the_margins_are_empty(): void
    {
        $html = '<html><head></head><body></body></html>';

        $this->assertSame($html, PageHtml::injectMargins($html, new Margins));
    }

    public function test_it_injects_the_font_face_in_the_head(): void
    {
        $html = PageHtml::injectFont('<html><head></head><body></body></html>', 'MyFont', '/tmp/fonts/myfont.ttf');

        $this->assertStringContainsString('@font-face', $html);
        $this->assertStringContainsString('font-family:"MyFont"', $html);
    }

    public function test_it_keeps_the_path_when_the_font_file_does_not_exist(): void
    {
        $html = PageHtml::injectFont('<html><head></head><body></body></html>', 'MyFont', '/does/not/exist.ttf');

        $this->assertStringContainsString('file:///does/not/exist.ttf', $html);
    }

    public function test_it_uses_the_real_path_of_an_existing_font(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'font-');
        file_put_contents($path, 'font');

        $html = PageHtml::injectFont('<html><head></head><body></body></html>', 'MyFont', $path);

        $this->assertStringContainsString('file://'.realpath($path), $html);

        @unlink($path);
    }

    public function test_it_applies_the_font_family(): void
    {
        $html = PageHtml::applyFontFamily('<html><head></head><body></body></html>', 'DejaVu Sans');

        $this->assertStringContainsString('font-family:"DejaVu Sans",sans-serif', $html);
    }

    public function test_it_adds_the_style_when_there_is_no_head(): void
    {
        $html = PageHtml::applyFontFamily('<p>plain</p>', 'DejaVu Sans');

        $this->assertStringStartsWith('<style>', $html);
    }

    public function test_it_removes_the_page_size_and_keeps_the_other_properties(): void
    {
        $html = '<html><head><style>@page { size: A4; margin: 10mm; }</style></head><body></body></html>';

        $stripped = PageHtml::stripPageSize($html);

        $this->assertStringNotContainsString('size: A4', $stripped);
        $this->assertStringContainsString('margin: 10mm', $stripped);
    }

    public function test_it_removes_the_page_rule_when_it_only_declares_the_size(): void
    {
        $html = '<html><head><style>@page { size: A4; }</style></head><body></body></html>';

        $this->assertStringNotContainsString('@page', PageHtml::stripPageSize($html));
    }

    public function test_it_does_not_touch_the_other_size_properties(): void
    {
        $html = '<html><head><style>@page { margin: 10mm; } .brand { font-size: 20px; }</style></head><body></body></html>';

        $this->assertStringContainsString('font-size: 20px', PageHtml::stripPageSize($html));
    }

    public function test_it_lists_the_page_sizes_of_the_document(): void
    {
        $html = '<style>@page { size: A4 landscape; } @page :first { size: A5; }</style>';

        $this->assertSame(['A4 landscape', 'A5'], PageHtml::pageSizes($html));
    }

    public function test_it_detects_the_page_sizes_that_conflict_with_the_document(): void
    {
        $this->assertSame(
            ['Letter'],
            PageHtml::conflictingPageSizes('<style>@page { size: Letter; }</style>', PaperSize::A4, Orientation::Portrait),
        );

        $this->assertSame(
            [],
            PageHtml::conflictingPageSizes('<style>@page { size: A4; }</style>', PaperSize::A4, Orientation::Portrait),
        );

        $this->assertSame(
            [],
            PageHtml::conflictingPageSizes('<style>@page { size: A4 landscape; }</style>', PaperSize::A4, Orientation::Landscape),
        );

        $this->assertSame(
            ['A4'],
            PageHtml::conflictingPageSizes('<style>@page { size: A4; }</style>', PaperSize::A4, Orientation::Landscape),
        );

        $this->assertSame(
            ['A6'],
            PageHtml::conflictingPageSizes('<style>@page { size: A6; }</style>', PaperSize::A4, Orientation::Portrait),
        );
    }

    public function test_it_warns_when_the_template_declares_another_paper(): void
    {
        $warnings = [];

        set_error_handler(function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        });

        try {
            $stripped = PageHtml::applyPaperSize('<style>@page { size: A5; }</style>', PaperSize::A4, Orientation::Portrait);
        } finally {
            restore_error_handler();
        }

        $this->assertStringNotContainsString('size: A5', $stripped);
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('A5', $warnings[0]);
    }
}
