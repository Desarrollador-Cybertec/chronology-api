<?php

namespace Tests\Feature\Import;

use App\Jobs\ProcessImportBatchJob;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCsvTest extends TestCase
{
    use RefreshDatabase;

    private \Illuminate\Filesystem\FilesystemAdapter $fakeDisk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeDisk = Storage::fake('csv_imports');
        Queue::fake();
    }

    private function validCsvContent(): string
    {
        return implode("\n", [
            'ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,Nombre personalizado,Fuente de datos,Gestión de informe,Temperatura,Anormal',
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 08:05:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 17:02:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
            "'1002,MARIA ELENA GOMEZ,InsummaBG,2026-01-15 07:58:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
            "'1002,MARIA ELENA GOMEZ,InsummaBG,2026-01-15 17:10:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
        ]);
    }

    private function uploadCsv(string $content, string $filename = 'marcaciones.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, $content);
    }

    public function test_superadmin_can_upload_csv(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $response = $this->actingAs($user)->postJson('/api/import', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id', 'uploaded_by', 'original_filename', 'file_hash',
                    'status', 'total_rows', 'processed_rows', 'failed_rows',
                ],
            ]);
    }

    public function test_manager_can_upload_csv(): void
    {
        $user = User::factory()->manager()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $response = $this->actingAs($user)->postJson('/api/import', [
            'file' => $file,
        ]);

        $response->assertStatus(201);
    }

    public function test_csv_creates_import_batch(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $this->assertDatabaseCount('import_batches', 1);
        $batch = ImportBatch::first();
        $this->assertEquals($user->id, $batch->uploaded_by);
        $this->assertEquals('marcaciones.csv', $batch->original_filename);
        $this->assertEquals(4, $batch->total_rows);
        $this->assertEquals(4, $batch->processed_rows);
        $this->assertEquals(0, $batch->failed_rows);
    }

    public function test_csv_stores_file_on_disk(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $batch = ImportBatch::first();
        $this->fakeDisk->assertExists($batch->stored_path);
    }

    public function test_csv_creates_raw_logs(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $this->assertDatabaseCount('raw_logs', 4);
    }

    public function test_csv_creates_unknown_employees(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', ['internal_id' => '1001']);
        $this->assertDatabaseHas('employees', ['internal_id' => '1002']);
    }

    public function test_csv_uses_existing_employee_if_found(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['internal_id' => '1001', 'first_name' => 'Carlos', 'last_name' => 'López']);

        $file = $this->uploadCsv($this->validCsvContent());
        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $this->assertDatabaseCount('employees', 2);
        $employee = Employee::where('internal_id', '1001')->first();
        $this->assertEquals('Carlos', $employee->first_name);
        $this->assertEquals('López', $employee->last_name);
    }

    public function test_csv_raw_logs_link_to_correct_employees(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $emp1 = Employee::where('internal_id', '1001')->first();
        $emp2 = Employee::where('internal_id', '1002')->first();

        $this->assertEquals(2, RawLog::where('employee_id', $emp1->id)->count());
        $this->assertEquals(2, RawLog::where('employee_id', $emp2->id)->count());
    }

    public function test_csv_dispatches_processing_job(): void
    {
        $user = User::factory()->superadmin()->create();
        $file = $this->uploadCsv($this->validCsvContent());

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        Queue::assertPushed(ProcessImportBatchJob::class, function ($job) {
            return $job->batch->id === ImportBatch::first()->id;
        });
    }

    public function test_duplicate_file_is_rejected(): void
    {
        $user = User::factory()->superadmin()->create();
        $content = $this->validCsvContent();

        $this->actingAs($user)->postJson('/api/import', [
            'file' => $this->uploadCsv($content),
        ]);

        $response = $this->actingAs($user)->postJson('/api/import', [
            'file' => $this->uploadCsv($content),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.0', 'Este archivo ya fue importado anteriormente.');

        $this->assertDatabaseCount('import_batches', 1);
    }

    public function test_invalid_csv_missing_columns_returns_error(): void
    {
        $user = User::factory()->superadmin()->create();
        $csv = "name,email\nJohn,john@test.com";
        $file = $this->uploadCsv($csv);

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(422);

        $batch = ImportBatch::first();
        $this->assertEquals('failed', $batch->status);
    }

    public function test_invalid_csv_bad_date_returns_error(): void
    {
        $user = User::factory()->superadmin()->create();
        $csv = "ID de persona,Nombre,Departamento,Hora\n'1001,TEST,IT,not-a-date";
        $file = $this->uploadCsv($csv);

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(422);
    }

    public function test_empty_csv_no_data_rows_returns_error(): void
    {
        $user = User::factory()->superadmin()->create();
        $csv = 'id,datetime';
        $file = $this->uploadCsv($csv);

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(422);
    }

    public function test_upload_without_file_returns_validation_error(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/import', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_unauthenticated_cannot_upload(): void
    {
        $file = $this->uploadCsv($this->validCsvContent());

        $response = $this->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(401);
    }

    public function test_superadmin_can_list_import_batches(): void
    {
        $user = User::factory()->superadmin()->create();
        ImportBatch::factory()->count(3)->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/import');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_superadmin_can_show_import_batch(): void
    {
        $user = User::factory()->superadmin()->create();
        $batch = ImportBatch::factory()->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/import/{$batch->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $batch->id);
    }

    public function test_csv_with_alternative_date_format_works(): void
    {
        $user = User::factory()->superadmin()->create();
        $csv = "ID de persona,Nombre,Departamento,Hora\n'1001,JUAN PEREZ,IT,15/01/2026 08:00:00\n'1001,JUAN PEREZ,IT,15/01/2026 17:00:00";
        $file = $this->uploadCsv($csv);

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('raw_logs', 2);
    }

    public function test_real_biometric_csv_imports_successfully(): void
    {
        $csvPath = base_path('tests/csv/algo.csv');
        $this->assertFileExists($csvPath, 'CSV real del biúmétrico no encontrado.');

        $user = User::factory()->superadmin()->create();
        $file = new \Illuminate\Http\UploadedFile(
            $csvPath,
            'Informe de los registros originales.csv',
            'text/plain',
            null,
            true,
        );

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        $response->assertStatus(201);

        $batch = ImportBatch::first();
        $this->assertEquals('processing', $batch->status);
        $this->assertGreaterThan(0, $batch->total_rows);
        $this->assertEquals($batch->total_rows, $batch->processed_rows);
        $this->assertEquals(0, $batch->failed_rows);
        $this->assertNull($batch->errors);

        $this->assertGreaterThan(0, \App\Models\Employee::count());
        $this->assertGreaterThan(0, \App\Models\RawLog::count());
        $this->assertEquals($batch->total_rows, \App\Models\RawLog::count());
    }

    public function test_import_index_returns_pagination_meta(): void
    {
        $user = User::factory()->superadmin()->create();
        ImportBatch::factory()->count(5)->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/import');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_import_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->superadmin()->create();
        ImportBatch::factory()->count(8)->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/import?per_page=3');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_import_index_supports_page_navigation(): void
    {
        $user = User::factory()->superadmin()->create();
        ImportBatch::factory()->count(5)->create(['uploaded_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/import?per_page=3&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_import_skips_duplicate_check_times_for_same_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create(['internal_id' => '1001']);

        // Pre-existing raw_log from a previous import
        $oldBatch = ImportBatch::factory()->create(['uploaded_by' => $user->id]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $oldBatch->id,
            'check_time' => '2026-01-15 08:05:00',
            'date_reference' => '2026-01-15',
        ]);

        // New CSV with same check_time for same employee
        $csv = implode("\n", [
            'ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,Nombre personalizado,Fuente de datos,Gestión de informe,Temperatura,Anormal',
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 08:05:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 17:02:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
        ]);
        $file = $this->uploadCsv($csv);

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        // 08:05:00 should NOT be duplicated; only 17:02:00 is new
        $this->assertEquals(
            2,
            RawLog::where('employee_id', $employee->id)->count(),
        );
    }

    public function test_import_cleans_old_raw_logs_for_overlapping_dates(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create(['internal_id' => '1001']);

        // Old batch with a phantom mark at 14:24:10
        $oldBatch = ImportBatch::factory()->create(['uploaded_by' => $user->id]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $oldBatch->id,
            'check_time' => '2026-01-15 14:24:10',
            'date_reference' => '2026-01-15',
        ]);

        // New CSV covering the same date with only one mark
        $csv = implode("\n", [
            'ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,Nombre personalizado,Fuente de datos,Gestión de informe,Temperatura,Anormal',
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 15:58:48,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
        ]);
        $file = $this->uploadCsv($csv);

        $response = $this->actingAs($user)->postJson('/api/import', ['file' => $file]);
        $response->assertStatus(201);

        // Old 14:24:10 mark should be removed; only 15:58:48 remains
        $logs = RawLog::where('employee_id', $employee->id)->get();

        $this->assertCount(1, $logs);
        $this->assertEquals('2026-01-15 15:58:48', $logs->first()->check_time->format('Y-m-d H:i:s'));
    }

    public function test_import_preserves_raw_logs_for_non_overlapping_dates(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create(['internal_id' => '1001']);

        // Old batch with a mark on Jan 14 (not covered by new CSV)
        $oldBatch = ImportBatch::factory()->create(['uploaded_by' => $user->id]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $oldBatch->id,
            'check_time' => '2026-01-14 08:00:00',
            'date_reference' => '2026-01-14',
        ]);

        // New CSV covering only Jan 15
        $csv = implode("\n", [
            'ID de persona,Nombre,Departamento,Hora,Estado de asistencia,Punto de verificación de asistencia,Nombre personalizado,Fuente de datos,Gestión de informe,Temperatura,Anormal',
            "'1001,JUAN CARLOS PEREZ,InsummaBG,2026-01-15 08:05:00,Nada,Cafeteria Principal_Puerta1,-,Registro de deslizamiento de tarjeta,-,-,-",
        ]);
        $file = $this->uploadCsv($csv);

        $this->actingAs($user)->postJson('/api/import', ['file' => $file]);

        // Jan 14 mark should still exist
        $this->assertTrue(
            RawLog::where('employee_id', $employee->id)
                ->whereDate('date_reference', '2026-01-14')
                ->exists(),
        );
        $this->assertEquals(2, RawLog::where('employee_id', $employee->id)->count());
    }
}
