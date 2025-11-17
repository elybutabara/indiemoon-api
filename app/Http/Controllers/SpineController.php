<?php

namespace App\Http\Controllers;

use App\Domain\Spine\SpineCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpineController extends Controller
{
    public function calculate(Request $request, SpineCalculator $calculator): JsonResponse
    {
        $validated = $request->validate([
            'page_count' => ['required', 'integer', 'min:1'],
            'paper_caliper_mm' => ['required', 'numeric', 'gt:0'],
        ]);

        $width = $calculator->widthMm(
            (int) $validated['page_count'],
            (float) $validated['paper_caliper_mm']
        );

        return response()->json([
            'width_mm' => $width,
        ]);
    }
}
