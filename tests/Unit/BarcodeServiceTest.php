<?php

namespace Tests\Unit;

use App\Domain\Barcode\BarcodeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BarcodeServiceTest extends TestCase
{
    public function test_it_generates_pdf_with_checksum(): void
    {
        $service = new BarcodeService();

        $pdf = $service->generate('400638133393');

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('4006381333931', $pdf);
    }

    public function test_it_appends_price_addon(): void
    {
        $service = new BarcodeService();

        $pdf = $service->generate('978030640615', '59995');

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Price 59995', $pdf);
    }

    public function test_invalid_addon_length_is_rejected(): void
    {
        $service = new BarcodeService();

        $this->expectException(InvalidArgumentException::class);
        $service->generate('4006381333931', '123');
    }
}
