<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Vellum\Config\Margins;
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
}
