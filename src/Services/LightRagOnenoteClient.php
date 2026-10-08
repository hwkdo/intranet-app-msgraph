<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Services;

use Hwkdo\IntranetAppMsgraph\Exceptions\LightRagSourceConflictException;
use Hwkdo\IntranetAppMsgraph\Support\OnenoteLightRagTarget;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LightRagOnenoteClient
{
    /**
     * @return array{track_id: string}
     */
    public function insertText(string $instance, string $text, string $fileSource): array
    {
        $response = $this->request()
            ->post($this->baseUrl($instance).'/documents/text', [
                'text' => $text,
                'file_source' => $fileSource,
            ]);

        if ($response->status() === 409) {
            throw new LightRagSourceConflictException($fileSource);
        }

        $response->throw();

        $trackId = $response->json('track_id');
        if (! is_string($trackId) || $trackId === '') {
            throw new RuntimeException('LightRAG hat keine track_id geliefert.');
        }

        return ['track_id' => $trackId];
    }

    public function reprocessFailed(string $instance): void
    {
        $this->request()
            ->post($this->baseUrl($instance).'/documents/reprocess_failed')
            ->throw();
    }

    /**
     * @return array{status: string, doc_id: ?string, error_message: ?string}
     */
    public function trackStatus(string $instance, string $trackId): array
    {
        try {
            $response = $this->request()
                ->get($this->baseUrl($instance).'/documents/track_status/'.rawurlencode($trackId))
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException('LightRAG-Status konnte nicht gelesen werden.', previous: $exception);
        }

        $document = $response->json('documents.0') ?? [];
        $status = is_array($document) ? (string) ($document['status'] ?? '') : '';

        return [
            'status' => $status,
            'doc_id' => is_array($document) && is_string($document['id'] ?? null) ? $document['id'] : null,
            'error_message' => is_array($document) && is_string($document['error_msg'] ?? null) ? $document['error_msg'] : null,
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $apiKey = trim((string) config('intranet-app-msgraph.lightrag.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('LIGHTRAG_API_KEY fehlt.');
        }

        return Http::withHeaders([
            'X-API-Key' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(60);
    }

    private function baseUrl(string $instance): string
    {
        return OnenoteLightRagTarget::url($instance);
    }
}
