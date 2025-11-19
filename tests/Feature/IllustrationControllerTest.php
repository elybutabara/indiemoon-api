<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IllustrationControllerTest extends TestCase
{
    public function test_it_generates_flux_illustration(): void
    {
        config()->set('services.illustration_broker.model', 'black-forest-labs/flux/dev');
        config()->set('services.illustration_broker.api_key', 'test-token');

        Http::fake([
            'api.replicate.com/v1/predictions' => Http::response([
                'id' => 'pred-123',
                'status' => 'starting',
            ], 201),
            'api.replicate.com/v1/predictions/pred-123' => Http::sequence()
                ->push([
                    'id' => 'pred-123',
                    'status' => 'processing',
                ])
                ->push([
                    'id' => 'pred-123',
                    'status' => 'succeeded',
                    'output' => ['https://replicate.delivery/123/image.png'],
                    'metrics' => ['predict_time' => 12.5],
                ]),
        ]);

        $payload = [
            'prompt' => 'A watercolor illustration of a cozy forest library',
            'aspect_ratio' => '3:4',
            'output_format' => 'png',
        ];

        $response = $this->postJson('/illustrations/generate', $payload);

        $response->assertOk()->assertJson([
            'prediction_id' => 'pred-123',
            'status' => 'succeeded',
            'images' => ['https://replicate.delivery/123/image.png'],
        ]);

        Http::assertSent(function (Request $request) use ($payload): bool {
            if ($request->url() !== 'https://api.replicate.com/v1/predictions') {
                return false;
            }

            $body = $request->data();
            $authorization = $request->header('Authorization')[0] ?? null;

            return $authorization === 'Token test-token'
                && ($body['input']['prompt'] ?? null) === $payload['prompt']
                && ($body['input']['aspect_ratio'] ?? null) === $payload['aspect_ratio'];
        });
    }

    public function test_it_validates_prompt(): void
    {
        $response = $this->postJson('/illustrations/generate', []);

        $response->assertUnprocessable();
    }
}
