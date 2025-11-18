<?php

namespace App\Domain\Cover;

use InvalidArgumentException;

class CoverTemplateEngine
{
    private const MM_TO_POINTS = 72.0 / 25.4;

    public function buildLayout(
        float $heightMm,
        float $backCoverWidthMm,
        float $spineWidthMm,
        float $frontCoverWidthMm,
        float $backFlapWidthMm = 0.0,
        float $frontFlapWidthMm = 0.0,
        float $bleedMm = 3.0,
    ): array {
        $this->assertNonNegative([$backFlapWidthMm, $frontFlapWidthMm, $bleedMm]);
        $this->assertPositive([$heightMm, $backCoverWidthMm, $spineWidthMm, $frontCoverWidthMm]);

        $panels = [];
        $cursor = $bleedMm;

        $panelDefinitions = [
            ['key' => 'back_flap', 'width' => $backFlapWidthMm, 'label' => 'Back Flap'],
            ['key' => 'back_cover', 'width' => $backCoverWidthMm, 'label' => 'Back Cover'],
            ['key' => 'spine', 'width' => $spineWidthMm, 'label' => 'Spine'],
            ['key' => 'front_cover', 'width' => $frontCoverWidthMm, 'label' => 'Front Cover'],
            ['key' => 'front_flap', 'width' => $frontFlapWidthMm, 'label' => 'Front Flap'],
        ];

        foreach ($panelDefinitions as $definition) {
            if ($definition['width'] <= 0.0) {
                continue;
            }

            $panels[] = [
                'name' => $definition['key'],
                'label' => $definition['label'],
                'x_mm' => $cursor,
                'y_mm' => $bleedMm,
                'width_mm' => $definition['width'],
                'height_mm' => $heightMm,
            ];

            $cursor += $definition['width'];
        }

        $totalWidthMm = $cursor + $bleedMm;
        $totalHeightMm = $heightMm + ($bleedMm * 2);

        return [
            'width_mm' => round($totalWidthMm, 2),
            'height_mm' => round($totalHeightMm, 2),
            'bleed_mm' => $bleedMm,
            'panels' => $panels,
        ];
    }

    public function renderSvg(array $layout): string
    {
        $this->assertLayoutShape($layout);

        $width = $layout['width_mm'];
        $height = $layout['height_mm'];
        $bleed = $layout['bleed_mm'];

        $svg = [];
        $svg[] = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%.2fmm" height="%.2fmm" viewBox="0 0 %.2f %.2f" role="img" aria-label="Cover template">', $width, $height, $width, $height);
        $svg[] = sprintf('<rect x="0" y="0" width="%.2f" height="%.2f" fill="#ffe9e9" stroke="#c0392b" stroke-width="0.4" stroke-dasharray="6 4" />', $width, $height);
        $svg[] = sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#ffffff" stroke="#2c3e50" stroke-width="0.4" />', $bleed, $bleed, $width - ($bleed * 2), $height - ($bleed * 2));

        $palette = ['#e8f1ff', '#eef8e8', '#fff7e6', '#e7f9f7', '#f6e8ff'];

        foreach ($layout['panels'] as $index => $panel) {
            $fill = $palette[$index % count($palette)];
            $svg[] = sprintf(
                '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="%s" stroke="#2c3e50" stroke-width="0.4" />',
                $panel['x_mm'],
                $panel['y_mm'],
                $panel['width_mm'],
                $panel['height_mm'],
                $fill,
            );

            $centerX = $panel['x_mm'] + ($panel['width_mm'] / 2);
            $centerY = $panel['y_mm'] + ($panel['height_mm'] / 2);

            $svg[] = sprintf(
                '<text x="%.2f" y="%.2f" text-anchor="middle" dominant-baseline="middle" fill="#2c3e50" font-family="Arial, sans-serif" font-size="10">%s</text>',
                $centerX,
                $centerY,
                $panel['label'],
            );
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    public function renderPdf(array $layout): string
    {
        $this->assertLayoutShape($layout);

        $widthPts = $this->mmToPoints($layout['width_mm']);
        $heightPts = $this->mmToPoints($layout['height_mm']);
        $bleedPts = $this->mmToPoints($layout['bleed_mm']);

        $content = [];
        $content[] = '0.5 w';
        $content[] = '0.94 0.94 0.94 rg';
        $content[] = '0.2 0.2 0.2 RG';
        $content[] = sprintf('0 0 %.2f %.2f re B', $widthPts, $heightPts);

        $content[] = '1 0 0 RG';
        $content[] = '[] 0 d';
        $content[] = sprintf('0 0 %.2f %.2f re S', $widthPts, $heightPts);

        $content[] = '0 0 0 RG';
        $content[] = '0.85 0.85 0.85 rg';
        $content[] = sprintf('%.2f %.2f %.2f %.2f re B', $bleedPts, $bleedPts, $widthPts - ($bleedPts * 2), $heightPts - ($bleedPts * 2));

        $palette = [
            [0.91, 0.95, 1],
            [0.93, 0.98, 0.9],
            [1, 0.97, 0.9],
            [0.91, 0.98, 0.96],
            [0.97, 0.91, 1],
        ];

        foreach ($layout['panels'] as $index => $panel) {
            $fill = $palette[$index % count($palette)];
            $x = $this->mmToPoints($panel['x_mm']);
            $y = $this->mmToPoints($panel['y_mm']);
            $w = $this->mmToPoints($panel['width_mm']);
            $h = $this->mmToPoints($panel['height_mm']);

            $content[] = sprintf('%.2f %.2f %.2f rg', $fill[0], $fill[1], $fill[2]);
            $content[] = '0.25 0.25 0.25 RG';
            $content[] = sprintf('%.2f %.2f %.2f %.2f re B', $x, $y, $w, $h);

            $labelX = $x + ($w / 2) - 18;
            $labelY = $y + ($h / 2) + 3;
            $content[] = '0 0 0 rg';
            $content[] = 'BT /F1 12 Tf';
            $content[] = sprintf('%.2f %.2f Td (%s) Tj', $labelX, $labelY, $panel['label']);
            $content[] = 'ET';
        }

        $contentStream = implode("\n", $content);

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>', $widthPts, $heightPts),
            4 => sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($contentStream), $contentStream),
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        return $this->assemblePdf($objects);
    }

    private function mmToPoints(float $value): float
    {
        return $value * self::MM_TO_POINTS;
    }

    private function assemblePdf(array $objects): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $number, $body);
        }

        $xrefPosition = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        foreach ($objects as $number => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", count($objects) + 1, $xrefPosition);

        return $pdf;
    }

    private function assertLayoutShape(array $layout): void
    {
        foreach (['width_mm', 'height_mm', 'bleed_mm', 'panels'] as $required) {
            if (! array_key_exists($required, $layout)) {
                throw new InvalidArgumentException('Layout is missing the '.$required.' key.');
            }
        }
    }

    private function assertPositive(array $values): void
    {
        foreach ($values as $value) {
            if ($value <= 0.0) {
                throw new InvalidArgumentException('Dimensions must be greater than zero.');
            }
        }
    }

    private function assertNonNegative(array $values): void
    {
        foreach ($values as $value) {
            if ($value < 0.0) {
                throw new InvalidArgumentException('Dimensions cannot be negative.');
            }
        }
    }
}
