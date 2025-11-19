<?php

namespace App\Domain\Illustration;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use RuntimeException;

class IllustrationBroker
{
    private const BASE_URL = 'https://api.replicate.com/v1';

    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function generate(string $prompt, array $options = []): array
    {
        $model = config('services.illustration_broker.model');
        $apiKey = config('services.illustration_broker.api_key');

        if (! $model) {
            throw new RuntimeException('Flux model is not configured.');
        }

        if (! $apiKey) {
            throw new RuntimeException('Replicate API key is not configured.');
        }

        $input = $this->buildInputPayload($prompt, $options);

        $prediction = $this->client($apiKey)->post('/predictions', [
            'version' => $model,
            'input' => $input,
        ])->throw()->json();

        $predictionId = $prediction['id'] ?? null;

        if (! $predictionId) {
            throw new RuntimeException('Flux prediction did not return an identifier.');
        }

        $maxAttempts = max((int) ($options['max_attempts'] ?? 30), 1);
        $intervalMs = max((int) ($options['poll_interval_ms'] ?? 750), 100);

        return $this->waitForPrediction($predictionId, $apiKey, $maxAttempts, $intervalMs);
    }

    private function buildInputPayload(string $prompt, array $options): array
    {
        $input = [
            'prompt' => $prompt,
            'negative_prompt' => $options['negative_prompt'] ?? null,
            'aspect_ratio' => $options['aspect_ratio'] ?? null,
            'output_format' => $options['output_format'] ?? 'png',
            'output_quality' => $options['output_quality'] ?? null,
            'guidance' => $options['guidance'] ?? null,
            'seed' => $options['seed'] ?? null,
            'steps' => $options['steps'] ?? null,
            'safety_tolerance' => $options['safety_tolerance'] ?? null,
        ];

        if (isset($options['extra_input']) && is_array($options['extra_input'])) {
            $input = array_merge($input, $options['extra_input']);
        }

        return array_filter($input, function ($value) {
            if (is_string($value)) {
                return $value !== '';
            }

            return $value !== null;
        });
    }

    private function waitForPrediction(string $predictionId, string $apiKey, int $maxAttempts, int $intervalMs): array
    {
        $terminalStatuses = ['succeeded', 'failed', 'canceled'];
        $prediction = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                usleep($intervalMs * 1000);
            }

            $prediction = $this->client($apiKey)
                ->get("/predictions/{$predictionId}")
                ->throw()
                ->json();

            if (in_array($prediction['status'] ?? '', $terminalStatuses, true)) {
                break;
            }
        }

        if (! $prediction || ! in_array($prediction['status'] ?? '', $terminalStatuses, true)) {
            throw new RuntimeException('Flux prediction did not complete before the timeout elapsed.');
        }

        if ($prediction['status'] !== 'succeeded') {
            $message = $prediction['error'] ?? 'Flux prediction failed.';
            throw new RuntimeException($message);
        }

        return [
            'id' => $prediction['id'],
            'status' => $prediction['status'],
            'output' => $prediction['output'] ?? [],
            'meta' => Arr::only($prediction, [
                'metrics',
                'logs',
                'created_at',
                'started_at',
                'completed_at',
            ]),
        ];
    }

    private function client(string $apiKey): PendingRequest
    {
        return $this->http->baseUrl(self::BASE_URL)
            ->withHeaders([
                'Authorization' => 'Token '.$apiKey,
                'Accept' => 'application/json',
            ])
            ->asJson();
    }
}
