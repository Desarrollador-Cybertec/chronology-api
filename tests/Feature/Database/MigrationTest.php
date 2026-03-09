<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password', 'role', 'remember_token', 'created_at', 'updated_at',
        ]));
    }

    public function test_employees_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('employees'));
        $this->assertTrue(Schema::hasColumns('employees', [
            'id', 'internal_id', 'first_name', 'last_name',
            'department', 'position', 'is_active', 'created_at', 'updated_at',
        ]));
    }

    public function test_shifts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('shifts'));
        $this->assertTrue(Schema::hasColumns('shifts', [
            'id', 'name', 'start_time', 'end_time',
            'crosses_midnight',
            'tolerance_minutes', 'overtime_enabled', 'overtime_min_block_minutes',
            'max_daily_overtime_minutes', 'is_active', 'created_at', 'updated_at',
        ]));
    }

    public function test_employee_shift_assignments_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('employee_shift_assignments'));
        $this->assertTrue(Schema::hasColumns('employee_shift_assignments', [
            'id', 'employee_id', 'shift_id', 'effective_date', 'end_date', 'created_at', 'updated_at',
        ]));
    }

    public function test_import_batches_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('import_batches'));
        $this->assertTrue(Schema::hasColumns('import_batches', [
            'id', 'uploaded_by', 'original_filename', 'stored_path', 'file_hash',
            'status', 'total_rows', 'processed_rows', 'failed_rows', 'errors',
            'processed_at', 'created_at', 'updated_at',
        ]));
    }

    public function test_raw_logs_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('raw_logs'));
        $this->assertTrue(Schema::hasColumns('raw_logs', [
            'id', 'employee_id', 'import_batch_id', 'check_time',
            'date_reference', 'original_line', 'created_at', 'updated_at',
        ]));
    }

    public function test_attendance_days_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_days'));
        $this->assertTrue(Schema::hasColumns('attendance_days', [
            'id', 'employee_id', 'date_reference', 'shift_id',
            'first_check_in', 'last_check_out', 'worked_minutes', 'overtime_minutes',
            'late_minutes', 'status', 'is_manually_edited', 'created_at', 'updated_at',
        ]));
    }

    public function test_attendance_edits_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_edits'));
        $this->assertTrue(Schema::hasColumns('attendance_edits', [
            'id', 'attendance_day_id', 'edited_by', 'field_changed',
            'old_value', 'new_value', 'reason', 'created_at', 'updated_at',
        ]));
    }

    public function test_system_settings_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('system_settings'));
        $this->assertTrue(Schema::hasColumns('system_settings', [
            'id', 'key', 'value', 'group', 'created_at', 'updated_at',
        ]));
    }

    public function test_personal_access_tokens_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
    }

    public function test_jobs_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    public function test_cache_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('cache'));
        $this->assertTrue(Schema::hasTable('cache_locks'));
    }
}
