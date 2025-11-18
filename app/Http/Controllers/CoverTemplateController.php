<?php

namespace App\Http\Controllers;

use App\Domain\Cover\CoverTemplateEngine;
use App\Http\Requests\CoverTemplateRequest;
use Illuminate\Http\JsonResponse;

class CoverTemplateController extends Controller
{
    public function generate(CoverTemplateRequest $request, CoverTemplateEngine $engine): JsonResponse
    {
        $validated = $request->validated();

        $layout = $engine->buildLayout(
            (float) $validated['height_mm'],
            (float) $validated['back_cover_mm'],
            (float) $validated['spine_mm'],
            (float) $validated['front_cover_mm'],
            (float) ($validated['back_flap_mm'] ?? 0.0),
            (float) ($validated['front_flap_mm'] ?? 0.0),
            (float) ($validated['bleed_mm'] ?? 3.0),
        );

        $svg = $engine->renderSvg($layout);
        $pdf = $engine->renderPdf($layout);

        return response()->json([
            'layout' => $layout,
            'svg' => $svg,
            'pdf_base64' => base64_encode($pdf),
        ]);
    }
}
