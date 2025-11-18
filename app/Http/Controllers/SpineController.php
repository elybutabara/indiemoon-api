<?php

namespace App\Http\Controllers;

use App\Domain\Spine\SpineCalculator;
use App\Http\Requests\SpineCalculateRequest;
use Illuminate\Http\JsonResponse;

class SpineController extends Controller
{
    public function calculate(SpineCalculateRequest $request, SpineCalculator $calculator): JsonResponse
    {
        $validated = $request->validated();

        $width = $calculator->widthMm(
            (int) $validated['page_count'],
            (float) $validated['paper_caliper_mm']
        );

        return response()->json([
            'width_mm' => $width,
        ]);
    }
}
