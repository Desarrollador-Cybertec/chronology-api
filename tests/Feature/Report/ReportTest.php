<?php

namespace Tests\Feature\Report;

use App\Jobs\GenerateReportJob;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    // ── Index ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_list_reports(): void
    {
        $user = User::factory()->superadmin()->create();
        Report::factory()->count(3)->create(['generated_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/reports');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_manager_can_list_reports(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->getJson('/api/reports');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_list_reports(): void
    {
        $response = $this->getJson('/api/reports');

        $response->assertStatus(401);
    }

    public function test_index_filters_by_type(): void
    {
        $user = User::factory()->superadmin()->create();
        Report::factory()->count(2)->create(['generated_by' => $user->id, 'type' => 'individual', 'employee_id' => Employee::factory()]);
        Report::factory()->count(3)->create(['generated_by' => $user->id, 'type' => 'general']);

        $response = $this->actingAs($user)->getJson('/api/reports?type=general');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $user = User::factory()->superadmin()->create();
        Report::factory()->count(2)->completed()->create(['generated_by' => $user->id]);
        Report::factory()->create(['generated_by' => $user->id, 'status' => 'pending']);

        $response = $this->actingAs($user)->getJson('/api/reports?status=completed');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── Store ─────────────────────────────────────────────────────────────

    public function test_superadmin_can_create_general_report(): void
    {
        Queue::fake();
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.type', 'general')
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_manager_can_create_individual_report(): void
    {
        Queue::fake();
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'individual',
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.type', 'individual')
            ->assertJsonPath('data.employee_code', $employee->internal_id);

        Queue::assertPushed(GenerateReportJob::class);
    }

    public function test_individual_report_requires_employee_id(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'individual',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_store_fails_when_date_to_before_date_from(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'date_from' => '2026-02-01',
            'date_to' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('date_to');
    }

    public function test_store_fails_with_invalid_type(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'invalid',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_general_report_nullifies_employee_id(): void
    {
        Queue::fake();
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/reports', [
            'type' => 'general',
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response->assertStatus(202);
        $this->assertNull(Report::first()->employee_id);
    }

    // ── Show ──────────────────────────────────────────────────────────────

    public function test_superadmin_can_view_report(): void
    {
        $user = User::factory()->superadmin()->create();
        $report = Report::factory()->completed()->create(['generated_by' => $user->id]);

        $response = $this->actingAs($user)->getJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_general_report_name_includes_date_range(): void
    {
        $user = User::factory()->superadmin()->create();
        $report = Report::factory()->create([
            'generated_by' => $user->id,
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response = $this->actingAs($user)->getJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Reporte general 2026-01-01 / 2026-01-31');
    }

    public function test_individual_report_name_includes_employee_name_and_date_range(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $report = Report::factory()->individual()->create([
            'generated_by' => $user->id,
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        $response = $this->actingAs($user)->getJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', "{$employee->full_name} 2026-01-01 / 2026-01-31");
    }

    // ── Destroy ───────────────────────────────────────────────────────────

    public function test_superadmin_can_delete_report(): void
    {
        $user = User::factory()->superadmin()->create();
        $report = Report::factory()->create(['generated_by' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/reports/{$report->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Reporte eliminado correctamente.');

        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    // ── Job ───────────────────────────────────────────────────────────────

    public function test_job_generates_individual_report(): void
    {
        $employee = Employee::factory()->create();
        $user = User::factory()->superadmin()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'date_reference' => '2026-01-15',
            'late_minutes' => 10,
            'overtime_minutes' => 30,
            'status' => 'present',
        ]);

        AttendanceDay::factory()->absent()->create([
            'employee_id' => $employee->id,
            'date_reference' => '2026-01-16',
        ]);

        $report = Report::factory()->individual()->create([
            'generated_by' => $user->id,
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();

        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->completed_at);
        $this->assertCount(2, $report->rows);
        $this->assertEquals($employee->full_name, $report->summary['employee_name']);
        $this->assertEquals(1, $report->summary['times_late']);
        $this->assertEquals(10, $report->summary['total_late_minutes']);
        $this->assertEquals(30, $report->summary['total_overtime_minutes']);
        $this->assertEquals(1, $report->summary['days_present']);
        $this->assertEquals(1, $report->summary['days_absent']);
    }

    public function test_job_generates_general_report(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $employee1->id,
            'date_reference' => '2026-01-15',
            'late_minutes' => 5,
            'overtime_minutes' => 60,
            'status' => 'present',
        ]);

        AttendanceDay::factory()->create([
            'employee_id' => $employee2->id,
            'date_reference' => '2026-01-15',
            'late_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'present',
        ]);

        $report = Report::factory()->create([
            'generated_by' => $user->id,
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();

        $this->assertEquals('completed', $report->status);
        $this->assertCount(2, $report->rows);
        $this->assertEquals(2, $report->summary['total_employees']);
        $this->assertEquals(2, $report->summary['total_days']);
        $this->assertEquals(1, $report->summary['total_late_entries']);
        $this->assertEquals(5, $report->summary['total_late_minutes']);
        $this->assertEquals(60, $report->summary['total_overtime_minutes']);
    }

    public function test_job_only_includes_days_in_date_range(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);

        AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'date_reference' => '2026-03-01',
            'status' => 'present',
        ]);

        $report = Report::factory()->individual()->create([
            'generated_by' => $user->id,
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();
        $this->assertCount(1, $report->rows);
    }

    public function test_job_marks_report_failed_on_error(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $report = Report::factory()->individual()->create([
            'generated_by' => $user->id,
            'employee_id' => $employee->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]);

        // Set employee_id to null so findOrFail() throws inside the job
        $report->updateQuietly(['employee_id' => null]);

        (new GenerateReportJob($report))->handle();

        $report->refresh();
        $this->assertEquals('failed', $report->status);
        $this->assertNotNull($report->error_message);
    }
}
