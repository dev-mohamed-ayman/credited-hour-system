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
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->unsignedInteger('section_from')->nullable()->after('end_time');
            $table->unsignedInteger('section_to')->nullable()->after('section_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->dropColumn(['section_from', 'section_to']);
        });
    }
};
