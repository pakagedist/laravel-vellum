<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vellum\Tests\TestCase;

final class OutputTest extends TestCase
{
    public function test_it_returns_the_bytes(): void
    {
        $bytes = $this->manager()->html('<p>x</p>')->driver('fake')->bytes();

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_saves_the_document_on_disk(): void
    {
        $path = $this->temporaryPath('output.pdf');

        $result = $this->manager()->html('<p>x</p>')->driver('fake')->save($path);

        $this->assertSame($path, $result);
        $this->assertFileExists($path);
        $this->assertStringStartsWith('%PDF-', (string) file_get_contents($path));

        @unlink($path);
    }

    public function test_it_streams_the_document(): void
    {
        $response = $this->manager()->html('<p>x</p>')->driver('fake')->stream();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_it_downloads_the_document(): void
    {
        $response = $this->manager()->html('<p>x</p>')->driver('fake')->filename('invoice.pdf')->download();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('attachment; filename="invoice.pdf"', $response->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_the_download_uses_the_given_filename(): void
    {
        $response = $this->manager()->html('<p>x</p>')->driver('fake')->download('other.pdf');

        $this->assertSame('attachment; filename="other.pdf"', $response->headers->get('content-disposition'));
    }

    public function test_the_result_exposes_the_size_and_the_duration(): void
    {
        $result = $this->manager()->html('<p>x</p>')->driver('fake')->render();

        $this->assertSame(strlen($result->bytes), $result->size());
        $this->assertGreaterThanOrEqual(0.0, $result->durationMs);
    }

    public function test_the_response_method_returns_a_download(): void
    {
        $response = $this->manager()->html('<p>x</p>')->driver('fake')->filename('a.pdf')->response();

        $this->assertSame('attachment; filename="a.pdf"', $response->headers->get('content-disposition'));
    }
}
