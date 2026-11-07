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
        Schema::create('student_term_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_closure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('cgpa', 4, 2);
            $table->unsignedSmallInteger('earned_hours');
            $table->string('outcome');
            $table->text('note')->nullable();

            $table->unsignedTinyInteger('warnings_before');
            $table->unsignedTinyInteger('warnings_after');
            $table->foreignId('issued_warning_id')->nullable()->constrained('student_warnings')->nullOnDelete();
            $table->json('cleared_warning_ids')->nullable();

            $table->foreignId('level_id_before')->nullable()->constrained('levels')->nullOnDelete();
            $table->foreignId('level_id_after')->nullable()->constrained('levels')->nullOnDelete();
            $table->string('study_status_before')->nullable();
            $table->string('study_status_after')->nullable();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->timestamps();

            $table->unique(['term_closure_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_term_records');
    }
};
