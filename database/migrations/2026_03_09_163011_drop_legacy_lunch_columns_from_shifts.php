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
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn([
                'lunch_required',
                'lunch_start_time',
                'lunch_end_time',
                'lunch_duration_minutes',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('lunch_required')->default(false);
            $table->time('lunch_start_time')->nullable();
            $table->time('lunch_end_time')->nullable();
            $table->unsignedSmallInteger('lunch_duration_minutes')->default(0);
        });
    }
};
