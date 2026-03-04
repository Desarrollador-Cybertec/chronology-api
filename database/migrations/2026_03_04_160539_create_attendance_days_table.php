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
        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('date_reference');
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->dateTime('first_check_in')->nullable();
            $table->dateTime('last_check_out')->nullable();
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->enum('status', ['present', 'absent', 'incomplete', 'rest', 'holiday'])->default('absent');
            $table->boolean('is_manually_edited')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'date_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_days');
    }
};
