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
        Schema::create('lecture_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->foreignId('year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('day');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['venue_id', 'day', 'start_time', 'end_time']);
            $table->index(['course_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['venue_id']);
            $table->dropForeign(['year_id']);
            $table->dropColumn(['course_id', 'venue_id', 'year_id', 'day', 'start_time', 'end_time']);
        });

        Schema::dropIfExists('lecture_schedules');
    }
};
