<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Support\Pdf;

use PHPUnit\Framework\TestCase;
use Vellum\Support\Pdf\PdfLexer;

final class PdfLexerTest extends TestCase
{
    public function test_it_reads_a_token(): void
    {
        $position = 0;

        $this->assertSame('12', PdfLexer::readToken('12 34', $position));
        $this->assertSame(2, $position);
    }

    public function test_it_reads_a_name(): void
    {
        $position = 0;

        $this->assertSame('Type', PdfLexer::readName('/Type/Catalog', $position));
        $this->assertSame(5, $position);
    }

    public function test_it_reads_a_name_as_a_value(): void
    {
        $position = 0;

        $this->assertSame('/OpenAction', PdfLexer::readValue('/OpenAction', $position));
        $this->assertSame(11, $position);
    }

    public function test_it_always_advances_the_position(): void
    {
        $content = '/OpenAction [3 0 R /XYZ null null 1]';

        $position = 0;
        $reads = 0;

        while ($position < strlen($content)) {
            $before = $position;

            PdfLexer::readValue($content, $position);

            $this->assertGreaterThan($before, $position, 'The reader did not advance the position.');

            $reads++;
        }

        $this->assertGreaterThan(1, $reads);
    }

    public function test_it_does_not_hang_on_an_open_action_array(): void
    {
        $content = '/OpenAction [3 0 R /XYZ null null 1]';

        $position = 0;

        $start = microtime(true);

        while ($position < strlen($content)) {
            PdfLexer::readValue($content, $position);

            if ($position >= strlen($content)) {
                break;
            }
        }

        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    public function test_it_parses_a_dictionary(): void
    {
        $entries = PdfLexer::parseDictionary('<< /Type /Catalog /Pages 2 0 R /Count 3 >>');

        $this->assertSame('/Catalog', $entries['Type']);
        $this->assertSame('2 0 R', $entries['Pages']);
        $this->assertSame('3', $entries['Count']);
    }

    public function test_it_builds_a_dictionary(): void
    {
        $this->assertSame('<< /Type /Catalog >>', PdfLexer::buildDictionary(['Type' => '/Catalog']));
    }

    public function test_it_parses_an_array(): void
    {
        $this->assertSame(['3 0 R', '4 0 R'], PdfLexer::parseArray('[3 0 R 4 0 R]'));
    }

    public function test_it_extracts_the_reference_number(): void
    {
        $this->assertSame(3, PdfLexer::referenceNumber('3 0 R'));
        $this->assertNull(PdfLexer::referenceNumber('/Type'));
        $this->assertNull(PdfLexer::referenceNumber(''));
    }

    public function test_it_encodes_a_text_string(): void
    {
        $encoded = PdfLexer::encodeTextString('Hola');

        $this->assertStringStartsWith('(', $encoded);
        $this->assertStringEndsWith(')', $encoded);
    }

    public function test_it_skips_the_comments(): void
    {
        $content = "% a comment\n12";

        $position = 0;
        PdfLexer::skipWhitespaceAndComments($content, $position);

        $this->assertSame('12', PdfLexer::readToken($content, $position));
    }
}
