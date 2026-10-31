<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResponsesClient
{
    public function request(string $model, string $instructions, array $input, string $name, array $schema, string $idempotencyKey): array
    {
        if (! config('analysis.processing_approved') || ! config('analysis.api_key') || ! $model) {
            throw new RuntimeException('provider_not_configured');
        }
        try {
            $response = Http::withToken(config('analysis.api_key'))->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->connectTimeout(5)->timeout(45)->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'store' => false,
                    'instructions' => $instructions,
                    'input' => $input,

                    'max_output_tokens' => 4000,

                    'text' => ['format' => [
                        'type' => 'json_schema',
                        'name' => $name,
                        'strict' => true,
                        'schema' => $schema,
                    ]],
                ]);
        } catch (ConnectionException) {
            throw new RuntimeException('provider_connection_failed');
        }
        if (! $response->successful()) {
            throw new RuntimeException($response->status() === 429 ? 'provider_rate_limited' : 'provider_rejected');
        }
        if (strlen($response->body()) > 150000) {
            throw new RuntimeException('provider_response_too_large');
        }
        $body = $response->json();
        if (! is_array($body) || ($body['status'] ?? null) !== 'completed' || ! is_array($body['output'] ?? null)) {
            throw new RuntimeException('provider_incomplete');
        }
        $texts = [];
        foreach ($body['output'] ?? [] as $output) {
            if (! is_array($output) || ! is_array($output['content'] ?? [])) {
                throw new RuntimeException('provider_invalid_json');
            }
            foreach ($output['content'] ?? [] as $content) {
                if (! is_array($content)) {
                    throw new RuntimeException('provider_invalid_json');
                }
                if (($content['type'] ?? '') === 'refusal') {
                    throw new RuntimeException('provider_refusal');
                }
                if (($content['type'] ?? '') === 'output_text') {
                    if (! isset($content['text']) || ! is_string($content['text'])) {
                        throw new RuntimeException('provider_invalid_json');
                    }
                    $texts[] = $content['text'];
                }
            }
        }
        try {
            $result = json_decode(implode('', $texts), true, 50, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('provider_invalid_json');
        }
        if (! is_array($result)) {
            throw new RuntimeException('provider_invalid_json');
        }

        return [
            'result' => $result,
            'usage' => isset($body['usage']) ? Arr::only($body['usage'], ['input_tokens', 'output_tokens', 'total_tokens']) : null,
        ];
    }
}
