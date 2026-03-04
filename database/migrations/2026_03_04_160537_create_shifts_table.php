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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->boolean('lunch_required')->default(false);
            $table->unsignedSmallInteger('lunch_duration_minutes')->default(0);
            $table->unsignedSmallInteger('tolerance_minutes')->default(0);
            $table->boolean('overtime_enabled')->default(false);
            $table->unsignedSmallInteger('overtime_min_block_minutes')->default(60);
            $table->unsignedSmallInteger('max_daily_overtime_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
