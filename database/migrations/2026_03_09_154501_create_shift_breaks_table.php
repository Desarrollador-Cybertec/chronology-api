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
        Schema::create('shift_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->string('type');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_breaks');
    }
};
