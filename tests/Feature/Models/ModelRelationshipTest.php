<?php

namespace Tests\Feature\Models;

use App\Models\AttendanceDay;
use App\Models\AttendanceEdit;
use App\Models\Employee;
use App\Models\EmployeeScheduleException;
use App\Models\EmployeeShiftAssignment;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_has_raw_logs(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();

        RawLog::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
        ]);

        $this->assertCount(3, $employee->rawLogs);
    }

    public function test_employee_has_attendance_days(): void
    {
        $employee = Employee::factory()->create();

        AttendanceDay::factory()->count(2)->sequence(
            ['date_reference' => now()->subDays(1)->toDateString()],
            ['date_reference' => now()->subDays(2)->toDateString()],
        )->create([
            'employee_id' => $employee->id,
        ]);

        $this->assertCount(2, $employee->refresh()->attendanceDays);
    }

    public function test_employee_has_shift_assignments(): void
    {
        $employee = Employee::factory()->create();

        EmployeeShiftAssignment::factory()->count(2)->create([
            'employee_id' => $employee->id,
        ]);

        $this->assertCount(2, $employee->shiftAssignments);
    }

    public function test_shift_has_assignments(): void
    {
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->count(3)->create([
            'shift_id' => $shift->id,
        ]);

        $this->assertCount(3, $shift->assignments);
    }

    public function test_import_batch_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $batch = ImportBatch::factory()->create(['uploaded_by' => $user->id]);

        $this->assertEquals($user->id, $batch->uploadedBy->id);
    }

    public function test_import_batch_has_raw_logs(): void
    {
        $batch = ImportBatch::factory()->create();

        RawLog::factory()->count(5)->create([
            'import_batch_id' => $batch->id,
        ]);

        $this->assertCount(5, $batch->rawLogs);
    }

    public function test_raw_log_belongs_to_employee(): void
    {
        $rawLog = RawLog::factory()->create();

        $this->assertInstanceOf(Employee::class, $rawLog->employee);
    }

    public function test_raw_log_belongs_to_import_batch(): void
    {
        $rawLog = RawLog::factory()->create();

        $this->assertInstanceOf(ImportBatch::class, $rawLog->importBatch);
    }

    public function test_attendance_day_belongs_to_employee(): void
    {
        $attendanceDay = AttendanceDay::factory()->create();

        $this->assertInstanceOf(Employee::class, $attendanceDay->employee);
    }

    public function test_attendance_day_has_edits(): void
    {
        $attendanceDay = AttendanceDay::factory()->create();

        AttendanceEdit::factory()->count(2)->create([
            'attendance_day_id' => $attendanceDay->id,
        ]);

        $this->assertCount(2, $attendanceDay->edits);
    }

    public function test_attendance_edit_belongs_to_day(): void
    {
        $edit = AttendanceEdit::factory()->create();

        $this->assertInstanceOf(AttendanceDay::class, $edit->attendanceDay);
    }

    public function test_attendance_edit_belongs_to_editor(): void
    {
        $edit = AttendanceEdit::factory()->create();

        $this->assertInstanceOf(User::class, $edit->editedBy);
    }

    public function test_employee_shift_assignment_belongs_to_employee(): void
    {
        $assignment = EmployeeShiftAssignment::factory()->create();

        $this->assertInstanceOf(Employee::class, $assignment->employee);
    }

    public function test_employee_shift_assignment_belongs_to_shift(): void
    {
        $assignment = EmployeeShiftAssignment::factory()->create();

        $this->assertInstanceOf(Shift::class, $assignment->shift);
    }

    public function test_user_has_import_batches(): void
    {
        $user = User::factory()->create();

        ImportBatch::factory()->count(2)->create([
            'uploaded_by' => $user->id,
        ]);

        $this->assertCount(2, $user->importBatches);
    }

    public function test_user_has_attendance_edits(): void
    {
        $user = User::factory()->create();

        AttendanceEdit::factory()->count(3)->create([
            'edited_by' => $user->id,
        ]);

        $this->assertCount(3, $user->attendanceEdits);
    }

    public function test_employee_full_name_attribute(): void
    {
        $employee = Employee::factory()->create([
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
        ]);

        $this->assertEquals('Juan Pérez', $employee->full_name);
    }

    public function test_user_role_helpers(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $manager = User::factory()->manager()->create();

        $this->assertTrue($superadmin->isSuperAdmin());
        $this->assertFalse($superadmin->isManager());

        $this->assertTrue($manager->isManager());
        $this->assertFalse($manager->isSuperAdmin());
    }

    public function test_employee_has_schedule_exceptions(): void
    {
        $employee = Employee::factory()->create();

        EmployeeScheduleException::factory()->count(2)->create([
            'employee_id' => $employee->id,
        ]);

        $this->assertCount(2, $employee->scheduleExceptions);
    }

    public function test_schedule_exception_belongs_to_employee(): void
    {
        $exception = EmployeeScheduleException::factory()->create();

        $this->assertInstanceOf(Employee::class, $exception->employee);
    }

    public function test_schedule_exception_belongs_to_shift(): void
    {
        $exception = EmployeeScheduleException::factory()->withShift()->create();

        $this->assertInstanceOf(Shift::class, $exception->shift);
    }
}
