<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Levels now carry the earned hours a student needs to *start* them (الثانية من 27،
 * الثالثة من 60 …) instead of the hours to leave them: each level's old promotion
 * hours move to the level after it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_hours')->nullable()->after('name');
        });

        $levels = DB::table('levels')->orderBy('id')->get(['id', 'promotion_hours'])->values();

        foreach ($levels as $index => $level) {
            if ($index > 0 && $levels[$index - 1]->promotion_hours !== null) {
                DB::table('levels')->where('id', $level->id)->update(['start_hours' => $levels[$index - 1]->promotion_hours]);
            }
        }

        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('promotion_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->unsignedSmallInteger('promotion_hours')->nullable()->after('name');
        });

        $levels = DB::table('levels')->orderBy('id')->get(['id', 'start_hours'])->values();

        foreach ($levels as $index => $level) {
            if (isset($levels[$index + 1]) && $levels[$index + 1]->start_hours !== null) {
                DB::table('levels')->where('id', $level->id)->update(['promotion_hours' => $levels[$index + 1]->start_hours]);
            }
        }

        Schema::table('levels', function (Blueprint $table) {
            $table->dropColumn('start_hours');
        });
    }
};
