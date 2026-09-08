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
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('year_id')->constrained()->restrictOnDelete();
            $table->string('semester');
            $table->string('type')->default('regular');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'year_id', 'semester', 'type']);
            $table->index(['year_id', 'semester', 'exam_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['year_id']);
            $table->dropColumn([
                'course_id',
                'year_id',
                'semester',
                'type',
                'exam_date',
                'start_time',
                'end_time',
                'status',
                'notes',
            ]);
        });

        Schema::dropIfExists('exam_sessions');
    }
};
