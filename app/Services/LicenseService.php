<?php

namespace App\Services;

use App\Exceptions\LicenseException;
use App\Exceptions\LicenseSystemUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private string $apiUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.subscription.api_url', ''), '/');
        $this->apiKey = config('services.subscription.api_key', '');
    }

    /**
     * Authorize an action by reserving a slot in the Subscription Manager.
     *
     * @return array{allowed: bool, remaining: int, limit: int, current: int, status: string}
     *
     * @throws LicenseException
     * @throws LicenseSystemUnavailableException
     */
    public function authorize(string $action, int $quantity = 1): array
    {
        Log::info('Subscription: authorizing action', [
            'action' => $action,
            'quantity' => $quantity,
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/api/internal/authorize", [
                'action' => $action,
                'quantity' => $quantity,
            ]);
        } catch (ConnectionException $e) {
            Log::error('Subscription: system unavailable', ['error' => $e->getMessage()]);

            throw new LicenseSystemUnavailableException;
        }

        if ($response->serverError()) {
            Log::error('Subscription: server error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new LicenseSystemUnavailableException;
        }

        $data = $response->json();

        if ($response->clientError() || ! ($data['allowed'] ?? false)) {
            $mapped = $this->mapReason($data['reason'] ?? '');

            Log::warning('Subscription: action blocked', [
                'action' => $action,
                'error_code' => $mapped['error_code'],
                'message' => $mapped['message'],
                'remaining' => $data['remaining'] ?? 0,
                'status' => $data['status'] ?? null,
            ]);

            throw new LicenseException($mapped['message'], $mapped['error_code']);
        }

        Log::info('Subscription: action authorized', [
            'action' => $action,
            'remaining' => $data['remaining'] ?? null,
            'current' => $data['current'] ?? null,
        ]);

        return $data;
    }

    /**
     * Map the `reason` field from the Management System to a standard error_code.
     *
     * @return array{error_code: string, message: string}
     */
    private function mapReason(string $reason): array
    {
        return match (true) {
            str_contains($reason, 'Limit exceeded') => [
                'error_code' => 'license_denied',
                'message' => 'El límite del plan actual ha sido alcanzado. Para continuar, actualiza tu suscripción.',
            ],
            str_contains($reason, 'Subscription expired') => [
                'error_code' => 'license_expired',
                'message' => 'La suscripción ha vencido. Para continuar usando el sistema, renueva tu plan.',
            ],
            str_contains($reason, 'suspended') => [
                'error_code' => 'license_suspended',
                'message' => 'La suscripción está suspendida. Contacta al administrador de tu cuenta para resolver el problema.',
            ],
            default => [
                'error_code' => 'license_unavailable',
                'message' => 'El sistema de licencias no está disponible en este momento. La operación fue bloqueada por seguridad.',
            ],
        };
    }

    /**
     * Report usage to the Subscription Manager after a successful execution.
     */
    public function reportUsage(string $metric, int $value, string $referenceId): void
    {
        Log::info('Subscription: reporting usage', [
            'metric' => $metric,
            'value' => $value,
            'reference_id' => $referenceId,
        ]);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post("{$this->apiUrl}/api/internal/usage", [
                'metric' => $metric,
                'value' => $value,
                'reference_id' => $referenceId,
            ]);

            if ($response->failed()) {
                Log::warning('Subscription: usage report failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'reference_id' => $referenceId,
                ]);
            }
        } catch (ConnectionException $e) {
            Log::warning('Subscription: could not report usage (system unavailable)', [
                'error' => $e->getMessage(),
                'reference_id' => $referenceId,
            ]);
        }
    }
}
