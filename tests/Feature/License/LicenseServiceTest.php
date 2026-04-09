<?php

namespace Tests\Feature\License;

use App\Exceptions\LicenseException;
use App\Exceptions\LicenseSystemUnavailableException;
use App\Services\LicenseService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.subscription.api_url' => 'https://managed.cyberteconline.com',
            'services.subscription.api_key' => 'test-api-key',
        ]);
    }

    // ── Authorize ─────────────────────────────────────────────────────────

    public function test_authorize_succeeds_when_allowed(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'reason' => null,
                'limit' => 5000,
                'current' => 3200,
                'remaining' => 1800,
                'status' => 'active',
            ], 200),
        ]);

        $service = new LicenseService;
        $result = $service->authorize('run_execution', 1);

        $this->assertTrue($result['allowed']);
        $this->assertEquals(1800, $result['remaining']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://managed.cyberteconline.com/api/internal/authorize'
                && $request['action'] === 'run_execution'
                && $request['quantity'] === 1
                && $request['consume'] === false
                && $request->hasHeader('X-API-Key', 'test-api-key');
        });
    }

    public function test_authorize_sends_consume_true_when_specified(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'remaining' => 10,
                'status' => 'active',
            ], 200),
        ]);

        $service = new LicenseService;
        $service->authorize('run_execution', 1, true, 'reprocess_42');

        Http::assertSent(function ($request) {
            return $request['action'] === 'run_execution'
                && $request['consume'] === true
                && $request['reference_id'] === 'reprocess_42';
        });
    }

    public function test_authorize_omits_reference_id_when_null(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'remaining' => 10,
                'status' => 'active',
            ], 200),
        ]);

        $service = new LicenseService;
        $service->authorize('run_execution', 1, true);

        Http::assertSent(function ($request) {
            return $request['consume'] === true
                && ! isset($request['reference_id']);
        });
    }

    public function test_authorize_sends_correct_action_for_import(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'remaining' => 10,
                'status' => 'active',
            ], 200),
        ]);

        $service = new LicenseService;
        $service->authorize('run_import', 1);

        Http::assertSent(function ($request) {
            return $request['action'] === 'run_import';
        });
    }

    public function test_authorize_sends_correct_action_for_report(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'remaining' => 10,
                'status' => 'active',
            ], 200),
        ]);

        $service = new LicenseService;
        $service->authorize('run_report', 1);

        Http::assertSent(function ($request) {
            return $request['action'] === 'run_report';
        });
    }

    public function test_authorize_throws_license_exception_when_not_allowed(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => false,
                'reason' => 'Limit exceeded',
                'remaining' => 0,
            ], 403),
        ]);

        $this->expectException(LicenseException::class);

        $service = new LicenseService;
        $service->authorize('run_execution', 1);
    }

    public function test_authorize_throws_license_exception_with_correct_error_type(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => false,
                'remaining' => 0,
                'reason' => 'Subscription expired. Growth actions are blocked.',
            ], 403),
        ]);

        try {
            $service = new LicenseService;
            $service->authorize('run_execution', 1);
            $this->fail('Expected LicenseException was not thrown.');
        } catch (LicenseException $e) {
            $this->assertEquals('license_expired', $e->errorCode);
        }
    }

    public function test_authorize_throws_unavailable_on_server_error(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(LicenseSystemUnavailableException::class);

        $service = new LicenseService;
        $service->authorize('run_execution', 1);
    }

    public function test_authorize_throws_unavailable_on_connection_failure(): void
    {
        Http::fake([
            'managed.cyberteconline.com/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $this->expectException(LicenseSystemUnavailableException::class);

        $service = new LicenseService;
        $service->authorize('run_execution', 1);
    }

    // ── Report Usage ──────────────────────────────────────────────────────

    public function test_report_usage_sends_correct_payload(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/usage' => Http::response([
                'recorded' => true,
                'current' => 3201,
                'period' => '2026-04',
            ], 200),
        ]);

        $service = new LicenseService;
        $service->reportUsage('execution', 1, 'import_batch_123');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://managed.cyberteconline.com/api/internal/usage'
                && $request['metric'] === 'execution'
                && $request['value'] === 1
                && $request['reference_id'] === 'import_batch_123'
                && $request->hasHeader('X-API-Key', 'test-api-key')
                && ! isset($request['installation_id'])
                && ! isset($request['product']);
        });
    }

    public function test_report_usage_does_not_throw_on_failure(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/usage' => Http::response('Server Error', 500),
        ]);

        $service = new LicenseService;

        // Should not throw - usage reporting is best-effort
        $service->reportUsage('execution', 1, 'import_batch_456');

        $this->assertTrue(true);
    }

    public function test_report_usage_does_not_throw_on_connection_failure(): void
    {
        Http::fake([
            'managed.cyberteconline.com/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);

        $service = new LicenseService;

        // Should not throw - usage reporting is best-effort
        $service->reportUsage('execution', 1, 'report_789');

        $this->assertTrue(true);
    }
}
