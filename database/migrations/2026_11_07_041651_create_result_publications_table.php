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
        Schema::create('result_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('year_id')->constrained()->cascadeOnDelete();
            $table->string('semester');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year_id', 'semester']);
        });

        // Students already saw every result before publishing existed: keep those terms visible.
        $now = now();

        DB::table('registrations')
            ->select('year_id', 'semester')
            ->distinct()
            ->get()
            ->each(fn ($term) => DB::table('result_publications')->insert([
                'year_id' => $term->year_id,
                'semester' => $term->semester,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_publications');
    }
};
