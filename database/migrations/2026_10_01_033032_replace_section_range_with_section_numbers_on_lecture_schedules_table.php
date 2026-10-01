<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->json('section_numbers')->nullable()->after('end_time');
        });

        DB::table('lecture_schedules')
            ->whereNotNull('section_from')
            ->whereNotNull('section_to')
            ->orderBy('id')
            ->each(function (object $schedule) {
                DB::table('lecture_schedules')->where('id', $schedule->id)->update([
                    'section_numbers' => json_encode(range((int) $schedule->section_from, (int) $schedule->section_to)),
                ]);
            });

        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->dropColumn(['section_from', 'section_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->unsignedInteger('section_from')->nullable()->after('end_time');
            $table->unsignedInteger('section_to')->nullable()->after('section_from');
        });

        DB::table('lecture_schedules')
            ->whereNotNull('section_numbers')
            ->orderBy('id')
            ->each(function (object $schedule) {
                $numbers = json_decode($schedule->section_numbers, true) ?: [null];

                DB::table('lecture_schedules')->where('id', $schedule->id)->update([
                    'section_from' => min($numbers),
                    'section_to' => max($numbers),
                ]);
            });

        Schema::table('lecture_schedules', function (Blueprint $table) {
            $table->dropColumn('section_numbers');
        });
    }
};
