<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->unsignedSmallInteger('early_departure_minutes')->default(0)->after('late_minutes');
            $table->unsignedSmallInteger('overtime_diurnal_minutes')->default(0)->after('overtime_minutes');
            $table->unsignedSmallInteger('overtime_nocturnal_minutes')->default(0)->after('overtime_diurnal_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_days', function (Blueprint $table) {
            $table->dropColumn([
                'early_departure_minutes',
                'overtime_diurnal_minutes',
                'overtime_nocturnal_minutes',
            ]);
        });
    }
};
