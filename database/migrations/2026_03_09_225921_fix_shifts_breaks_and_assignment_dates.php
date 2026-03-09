<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── Upsert shifts with correct overtime ─────────────────
        DB::table('shifts')->updateOrInsert(
            ['name' => 'Horario 1'],
            [
                'start_time' => '07:00',
                'end_time' => '17:00',
                'tolerance_minutes' => 10,
                'crosses_midnight' => false,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 30,
                'max_daily_overtime_minutes' => 120,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        // Turno 2 was created via the UI in production — only update it if it exists
        $turno2Id = DB::table('shifts')->where('name', 'Turno 2')->value('id');
        if ($turno2Id) {
            DB::table('shifts')->where('id', $turno2Id)->update([
                'start_time' => '08:00',
                'end_time' => '17:00',
                'tolerance_minutes' => 10,
                'crosses_midnight' => false,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 30,
                'max_daily_overtime_minutes' => 120,
                'is_active' => true,
                'updated_at' => now(),
            ]);
        }

        // ── Upsert shift breaks ─────────────────────────────────
        $horario1Id = DB::table('shifts')->where('name', 'Horario 1')->value('id');

        if ($horario1Id) {
            DB::table('shift_breaks')->where('shift_id', $horario1Id)->delete();
            DB::table('shift_breaks')->insert([
                ['shift_id' => $horario1Id, 'type' => 'morning_snack', 'start_time' => '09:00', 'end_time' => '09:15', 'duration_minutes' => 15, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['shift_id' => $horario1Id, 'type' => 'lunch', 'start_time' => '13:00', 'end_time' => '13:30', 'duration_minutes' => 30, 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['shift_id' => $horario1Id, 'type' => 'afternoon_snack', 'start_time' => '16:00', 'end_time' => '16:15', 'duration_minutes' => 15, 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if ($turno2Id) {
            DB::table('shift_breaks')->where('shift_id', $turno2Id)->delete();
            DB::table('shift_breaks')->insert([
                ['shift_id' => $turno2Id, 'type' => 'morning_snack', 'start_time' => '09:00', 'end_time' => '09:15', 'duration_minutes' => 15, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
                ['shift_id' => $turno2Id, 'type' => 'lunch', 'start_time' => '14:00', 'end_time' => '14:30', 'duration_minutes' => 30, 'position' => 2, 'created_at' => now(), 'updated_at' => now()],
                ['shift_id' => $turno2Id, 'type' => 'afternoon_snack', 'start_time' => '16:00', 'end_time' => '16:15', 'duration_minutes' => 15, 'position' => 3, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ── Update system settings ──────────────────────────────
        $settings = [
            ['key' => 'auto_assign_tolerance_minutes', 'value' => '35', 'group' => 'attendance'],
            ['key' => 'data_retention_months', 'value' => '2', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now()])
            );
        }

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
        // Remove breaks only; restoring effective_dates would require a snapshot
        DB::table('shift_breaks')
            ->whereIn('shift_id', DB::table('shifts')->whereIn('name', ['Horario 1', 'Turno 2'])->pluck('id'))
            ->delete();
    }
};
