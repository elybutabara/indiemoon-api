<?php

namespace Tests\Unit;

use App\Domain\Export\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PdfEngineS3Test extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_stores_generated_pdf_on_s3_disk(): void
    {
        config([
            'export.pdf.disk' => 's3',
            'export.pdf.path_prefix' => 'exports/pdf',
        ]);

        Storage::fake('s3');

        $engine = Mockery::mock(PdfEngine::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $engine->shouldReceive('runPagedJs')
            ->once()
            ->andReturnUsing(function (string $htmlPath, string $outputPdfPath) {
                file_put_contents($outputPdfPath, 'pdf');
            });

        $engine->shouldReceive('runGhostscriptPdfx')
            ->once()
            ->andReturnUsing(function (string $inputPdf, string $outputPdfx) {
                file_put_contents($outputPdfx, 'pdfx');
            });

        $storagePath = $engine->generateFromHtml('<html></html>', 'My Document.pdf');

        Storage::disk('s3')->assertExists($storagePath);
        $this->assertStringStartsWith('exports/pdf/', $storagePath);
    }
}
