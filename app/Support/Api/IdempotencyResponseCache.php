<?php

namespace App\Support\Api;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IdempotencyResponseCache
{
    private const string HeaderName = 'X-Idempotency-Key';

    private const int LockSeconds = 10;

    private const int ResponseMinutes = 10;

    /**
     * Run one API write action once for the same authenticated actor and client-generated key.
     */
    public function run(Request $request, string $action, Closure $callback): JsonResponse
    {
        $idempotencyKey = $this->idempotencyKeyFromRequest($request);

        // Requests without an idempotency key should continue through the normal write flow.
        if ($idempotencyKey === null) {
            return $callback();
        }

        $cacheKey = $this->cacheKey($request, $action, $idempotencyKey);
        $cachedResponse = $this->cachedResponse($cacheKey);

        // Reuse the first successful response so transport retries stay safe.
        if ($cachedResponse !== null) {
            return $this->jsonResponse($cachedResponse);
        }

        return Cache::lock($cacheKey.':lock', self::LockSeconds)
            ->block(self::LockSeconds, function () use ($cacheKey, $callback): JsonResponse {
                $cachedResponse = $this->cachedResponse($cacheKey);

                // Re-check inside the lock so simultaneous retries do not execute twice.
                if ($cachedResponse !== null) {
                    return $this->jsonResponse($cachedResponse);
                }

                $response = $callback();

                $this->storeResponse($cacheKey, $response);

                return $response;
            });
    }

    /**
     * Build the shared cache key for one user action and client-generated idempotency key.
     */
    private function cacheKey(Request $request, string $action, string $idempotencyKey): string
    {
        $actorKey = (string) ($request->user()?->getAuthIdentifier() ?: $request->ip());

        return 'idempotency:'.$actorKey.':'.$action.':'.sha1($request->method().'|'.$request->path().'|'.$idempotencyKey);
    }

    /**
     * Return the previously stored API response when one exists for this idempotency key.
     *
     * @return array{payload: array<string, mixed>, status: int}|null
     */
    private function cachedResponse(string $cacheKey): ?array
    {
        $cachedResponse = Cache::get($cacheKey);

        // Ignore malformed cache entries and let the request execute normally.
        if (! is_array($cachedResponse) || ! isset($cachedResponse['payload'], $cachedResponse['status'])) {
            return null;
        }

        return [
            'payload' => is_array($cachedResponse['payload']) ? $cachedResponse['payload'] : [],
            'status' => (int) $cachedResponse['status'],
        ];
    }

    /**
     * Store the JSON response body and status for short-lived replay protection.
     */
    private function storeResponse(string $cacheKey, JsonResponse $response): void
    {
        Cache::put($cacheKey, [
            'payload' => $response->getData(true),
            'status' => $response->getStatusCode(),
        ], now()->addMinutes(self::ResponseMinutes));
    }

    /**
     * Rebuild a cached JSON response so repeat requests receive the same API payload.
     *
     * @param  array{payload: array<string, mixed>, status: int}  $cachedResponse
     */
    private function jsonResponse(array $cachedResponse): JsonResponse
    {
        return response()->json($cachedResponse['payload'], $cachedResponse['status']);
    }

    /**
     * Read the client-generated idempotency key header when one is present.
     */
    private function idempotencyKeyFromRequest(Request $request): ?string
    {
        $idempotencyKey = trim((string) $request->header(self::HeaderName, ''));

        // Empty keys should not change the normal request behavior.
        if ($idempotencyKey === '') {
            return null;
        }

        return substr($idempotencyKey, 0, 150);
    }
}
