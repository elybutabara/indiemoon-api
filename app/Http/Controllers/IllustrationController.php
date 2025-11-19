<?php

namespace App\Http\Controllers;

use App\Domain\Illustration\IllustrationBroker;
use App\Http\Requests\IllustrationGenerateRequest;
use Illuminate\Http\JsonResponse;

class IllustrationController extends Controller
{
    public function generate(IllustrationGenerateRequest $request, IllustrationBroker $broker): JsonResponse
    {
        $validated = $request->validated();
        $options = $request->safe()->except(['prompt']);

        $result = $broker->generate($validated['prompt'], $options);

        return response()->json([
            'prediction_id' => $result['id'],
            'status' => $result['status'],
            'images' => $result['output'],
            'meta' => $result['meta'],
        ]);
    }
}
