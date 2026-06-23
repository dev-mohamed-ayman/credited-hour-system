<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_registration_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->enum('term_type', ['first', 'second']);
            $table->unsignedInteger('max_optional_courses');
            $table->unique(['level_id', 'term_type']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_registration_settings');
    }
};
