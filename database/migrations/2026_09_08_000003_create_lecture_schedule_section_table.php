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
        Schema::create('lecture_schedule_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecture_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unique(['lecture_schedule_id', 'section_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecture_schedule_section', function (Blueprint $table) {
            $table->dropForeign(['lecture_schedule_id']);
            $table->dropForeign(['section_id']);
            $table->dropColumn(['lecture_schedule_id', 'section_id']);
        });

        Schema::dropIfExists('lecture_schedule_section');
    }
};
