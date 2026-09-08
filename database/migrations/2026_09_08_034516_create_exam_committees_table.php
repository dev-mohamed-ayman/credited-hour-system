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
        Schema::create('exam_committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->timestamps();

            $table->unique(['exam_session_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_committees', function (Blueprint $table) {
            $table->dropForeign(['exam_session_id']);
            $table->dropForeign(['venue_id']);
            $table->dropColumn(['exam_session_id', 'venue_id', 'name', 'capacity']);
        });

        Schema::dropIfExists('exam_committees');
    }
};
