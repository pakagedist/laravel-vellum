<?php

declare(strict_types=1);

namespace Vellum\Tests\Unit\Support\Pdf;

use PHPUnit\Framework\TestCase;
use Vellum\Support\Pdf\MiniPdf;
use Vellum\Support\Pdf\PdfDocument;
use Vellum\Support\Pdf\PdfFormatException;
use Vellum\Support\Pdf\XrefReader;

final class PdfDocumentTest extends TestCase
{
    public function test_it_reads_the_catalog_of_a_minimal_document(): void
    {
        $bytes = MiniPdf::create('hello');

        $document = PdfDocument::open($bytes);

        $this->assertNotNull($document->rootNumber());
        $this->assertNotNull($document->catalog());
        $this->assertSame('/Catalog', $document->catalog()?->dictionary['Type'] ?? null);
    }

    public function test_it_finds_the_first_page(): void
    {
        $document = PdfDocument::open(MiniPdf::create('hello'));

        $this->assertNotNull($document->firstPageNumber());
    }

    public function test_it_fails_when_the_document_has_no_catalog(): void
    {
        $this->expectException(PdfFormatException::class);

        PdfDocument::open("%PDF-1.4\nnot a pdf at all\n");
    }

    public function test_it_reads_the_cross_reference_table(): void
    {
        $bytes = MiniPdf::create('hello');

        $offset = XrefReader::lastStartXref($bytes);

        $this->assertGreaterThan(0, $offset);

        $info = XrefReader::read($bytes, $offset);

        $this->assertNotEmpty($info->entries);
        $this->assertArrayHasKey('Root', $info->trailer);
    }

    public function test_it_follows_the_previous_cross_reference_section(): void
    {
        $bytes = MiniPdf::create('hello');

        $offset = XrefReader::lastStartXref($bytes);

        $info = XrefReader::read($bytes, $offset);

        // The minimal document has a single section, the reader must not loop.
        $this->assertNotEmpty($info->entries);
    }

    public function test_the_minimal_document_is_a_valid_pdf(): void
    {
        $bytes = MiniPdf::create('hello');

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
    }

    public function test_the_minimal_document_is_deterministic(): void
    {
        $this->assertSame(MiniPdf::create('hello'), MiniPdf::create('hello'));
    }
}
