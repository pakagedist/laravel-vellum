<?php

declare(strict_types=1);

namespace Vellum\Tests\Feature;

use Vellum\Contracts\Driver;
use Vellum\Facades\Pdf;
use Vellum\Tests\TestCase;

final class FacadeTest extends TestCase
{
    public function test_it_renders_a_document_through_the_facade(): void
    {
        $bytes = Pdf::html('<h1>Hello</h1>')->driver('fake')->render()->bytes;

        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    public function test_it_exposes_the_manager_through_the_facade(): void
    {
        $this->assertTrue(Pdf::hasDriver('fake'));
        $this->assertSame('fake', Pdf::driver('fake')->name());
    }

    public function test_it_registers_a_custom_driver_through_the_facade(): void
    {
        Pdf::extend('custom', fn (): Driver => $this->manager()->driver('fake'));

        $this->assertTrue(Pdf::hasDriver('custom'));
    }
}
