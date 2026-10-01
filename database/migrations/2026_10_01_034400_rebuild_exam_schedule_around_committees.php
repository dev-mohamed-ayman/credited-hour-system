<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exam scheduling moves from "one session per course with auto-distributed
 * committees" to "term-wide committees with hand-picked students (by code),
 * each committee carrying its own exam timetable".
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('exam_seat_assignments');
        Schema::dropIfExists('exam_committees');
        Schema::dropIfExists('exam_sessions');

        Schema::create('exam_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')->constrained()->restrictOnDelete();
            $table->string('semester');
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year_id', 'semester', 'name']);
        });

        Schema::create('exam_committee_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seat_number');
            $table->timestamps();

            $table->unique(['exam_committee_id', 'student_id']);
            $table->unique(['exam_committee_id', 'seat_number']);
        });

        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('type')->default('regular');
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['exam_committee_id', 'course_id', 'type']);
            $table->index(['exam_date', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exam_committee_students');
        Schema::dropIfExists('exam_committees');

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

        Schema::create('exam_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->unique(['exam_session_id', 'name']);
        });

        Schema::create('exam_seat_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('seat_number');
            $table->timestamps();

            $table->unique(['exam_session_id', 'student_id']);
            $table->unique(['exam_committee_id', 'seat_number']);
        });
    }
};
