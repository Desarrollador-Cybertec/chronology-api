<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Backfill effective_date on auto-assigned shifts ─────
        // Set each assignment's effective_date to the employee's earliest raw_log date,
        // so all historical attendance records are covered by the assignment.
        DB::statement('
            UPDATE employee_shift_assignments
            SET effective_date = (
                SELECT MIN(date_reference)
                FROM raw_logs
                WHERE raw_logs.employee_id = employee_shift_assignments.employee_id
            )
            WHERE EXISTS (
                SELECT 1 FROM raw_logs
                WHERE raw_logs.employee_id = employee_shift_assignments.employee_id
                  AND raw_logs.date_reference < employee_shift_assignments.effective_date
            )
        ');
    }

    public function down(): void
    {
        //
    }
};
