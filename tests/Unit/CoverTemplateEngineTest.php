<?php

namespace Tests\Unit;

use App\Domain\Cover\CoverTemplateEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CoverTemplateEngineTest extends TestCase
{
    public function test_it_builds_layout_with_flaps(): void
    {
        $engine = new CoverTemplateEngine();

        $layout = $engine->buildLayout(220, 140, 18, 140, 40, 30, 3);

        $this->assertSame(374.0, $layout['width_mm']);
        $this->assertSame(226.0, $layout['height_mm']);
        $this->assertCount(5, $layout['panels']);
        $this->assertSame('back_cover', $layout['panels'][1]['name']);
        $this->assertSame(43.0, $layout['panels'][1]['x_mm']);
    }

    public function test_it_generates_svg_and_pdf(): void
    {
        $engine = new CoverTemplateEngine();

        $layout = $engine->buildLayout(200, 130, 15, 130, 0, 0, 2.5);

        $svg = $engine->renderSvg($layout);
        $pdf = $engine->renderPdf($layout);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('Front Cover', $svg);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
    }

    public function test_negative_values_are_rejected(): void
    {
        $engine = new CoverTemplateEngine();

        $this->expectException(InvalidArgumentException::class);

        $engine->buildLayout(0, 100, 10, 100, -1, 0, 3);
    }
}
