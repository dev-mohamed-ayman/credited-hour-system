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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_seat_assignments', function (Blueprint $table) {
            $table->dropForeign(['exam_session_id']);
            $table->dropForeign(['exam_committee_id']);
            $table->dropForeign(['student_id']);
            $table->dropColumn(['exam_session_id', 'exam_committee_id', 'student_id', 'seat_number']);
        });

        Schema::dropIfExists('exam_seat_assignments');
    }
};
