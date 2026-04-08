<?php

namespace Tests\Feature\License;

use App\Jobs\GenerateReportJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LicenseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.subscription.api_url' => 'https://managed.cyberteconline.com',
            'services.subscription.api_key' => 'test-api-key',
        ]);
    }

    private function fakeLicenseAllowed(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => true,
                'remaining' => 10,
                'limit' => 5000,
                'current' => 4990,
                'status' => 'active',
            ], 200),
            'managed.cyberteconline.com/api/internal/usage' => Http::response([
                'recorded' => true,
                'current' => 4991,
                'period' => '2026-04',
            ], 200),
        ]);
    }

    private function fakeLicenseDenied(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/authorize' => Http::response([
                'allowed' => false,
                'remaining' => 0,
                'reason' => 'Limit exceeded',
            ], 403),
        ]);
    }

    private function fakeLicenseUnavailable(): void
    {
        Http::fake([
            'managed.cyberteconline.com/*' => function () {
                throw new ConnectionException('Connection refused');
            },
        ]);
    }

    private function validCsvContent(): string
    {
        return implode("\n", [
            'ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,Nombre personalizado,Fuente de datos,Gestión de informe,Temperatura,Anormal',
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 08:05:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
            "'1002,MARIA ELENA GOMEZ,InsummaBG,2026-01-15 07:58:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
        ]);
    }

    // ── Import: Store ─────────────────────────────────────────────────────

    public function test_import_succeeds_when_license_allows(): void
    {
        $this->fakeLicenseAllowed();
        Storage::fake('csv_imports');
        Queue::fake();

        $user = User::factory()->superadmin()->create();
        $file = UploadedFile::fake()->createWithContent('test.csv', $this->validCsvContent());

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(201);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/authorize')
                && $request['action'] === 'run_import';
        });
    }

    public function test_import_blocked_when_license_denies(): void
    {
        $this->fakeLicenseDenied();
        Storage::fake('csv_imports');

        $user = User::factory()->superadmin()->create();
        $file = UploadedFile::fake()->createWithContent('test.csv', $this->validCsvContent());

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'license_denied');

        $this->assertDatabaseCount('import_batches', 0);
    }

    public function test_import_fails_when_license_system_unavailable(): void
    {
        $this->fakeLicenseUnavailable();
        Storage::fake('csv_imports');

        $user = User::factory()->superadmin()->create();
        $file = UploadedFile::fake()->createWithContent('test.csv', $this->validCsvContent());

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(503)
            ->assertJsonPath('error_code', 'license_unavailable');
    }

    // ── Import: Reprocess ─────────────────────────────────────────────────

    public function test_reprocess_succeeds_when_license_allows(): void
    {
        $this->fakeLicenseAllowed();
        Queue::fake();

        $user = User::factory()->superadmin()->create();
        $batch = ImportBatch::factory()->create(['uploaded_by' => $user->id, 'status' => 'completed']);

        $response = $this->actingAs($user)->postJson("/api/import/{$batch->id}/reprocess");

        $response->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/authorize')
                && $request['action'] === 'run_execution';
        });
    }

    public function test_reprocess_blocked_when_license_denies(): void
    {
        $this->fakeLicenseDenied();

        $user = User::factory()->superadmin()->create();
        $batch = ImportBatch::factory()->create(['uploaded_by' => $user->id, 'status' => 'completed']);

        $response = $this->actingAs($user)->postJson("/api/import/{$batch->id}/reprocess");

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'license_denied');
    }

    // ── Reports: Store ────────────────────────────────────────────────────

    public function test_report_creation_succeeds_when_license_allows(): void
    {
        $this->fakeLicenseAllowed();
        Queue::fake();

        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(GenerateReportJob::class);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/authorize')
                && $request['action'] === 'run_report';
        });
    }

    public function test_report_creation_blocked_when_license_denies(): void
    {
        $this->fakeLicenseDenied();

        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'license_denied');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_report_creation_fails_when_license_system_unavailable(): void
    {
        $this->fakeLicenseUnavailable();

        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('error_code', 'license_unavailable');
    }

    // ── Concurrent / Duplicate ────────────────────────────────────────────

    public function test_duplicate_reference_id_sends_same_id(): void
    {
        Http::fake([
            'managed.cyberteconline.com/api/internal/usage' => Http::response([
                'recorded' => true,
                'current' => 3201,
                'period' => '2026-04',
            ], 200),
        ]);

        $service = new LicenseService;

        // Same reference_id sent twice - Subscription Manager handles idempotency
        $service->reportUsage('execution', 1, 'import_batch_42');
        $service->reportUsage('execution', 1, 'import_batch_42');

        Http::assertSentCount(2);
    }
}
