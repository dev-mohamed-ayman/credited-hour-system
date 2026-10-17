<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every grade its real GPA points and mark range (until now `order`
 * doubled as the points, as whole numbers only) and loads the institute's
 * scale carried over from the legacy system into an already populated
 * grades table. Fresh installs get the scale from the seeders.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{name: string, points: float|null, min_score: float|null, max_score: float|null, counts_in_gpa: bool}>
     */
    private array $scale = [
        ['name' => 'A+', 'points' => 4.00, 'min_score' => 95, 'max_score' => 100, 'counts_in_gpa' => true],
        ['name' => 'A', 'points' => 3.70, 'min_score' => 90, 'max_score' => 94, 'counts_in_gpa' => true],
        ['name' => 'B+', 'points' => 3.40, 'min_score' => 85, 'max_score' => 89, 'counts_in_gpa' => true],
        ['name' => 'B', 'points' => 3.00, 'min_score' => 80, 'max_score' => 84, 'counts_in_gpa' => true],
        ['name' => 'C+', 'points' => 2.70, 'min_score' => 72, 'max_score' => 79, 'counts_in_gpa' => true],
        ['name' => 'C', 'points' => 2.30, 'min_score' => 65, 'max_score' => 71, 'counts_in_gpa' => true],
        ['name' => 'D+', 'points' => 2.20, 'min_score' => 55, 'max_score' => 64, 'counts_in_gpa' => true],
        ['name' => 'D', 'points' => 2.00, 'min_score' => 50, 'max_score' => 54, 'counts_in_gpa' => true],
        ['name' => 'F', 'points' => 0.00, 'min_score' => 0, 'max_score' => 49, 'counts_in_gpa' => true],
        ['name' => 'FX', 'points' => 0.00, 'min_score' => null, 'max_score' => null, 'counts_in_gpa' => true],
        ['name' => 'IC', 'points' => null, 'min_score' => null, 'max_score' => null, 'counts_in_gpa' => false],
        ['name' => 'W', 'points' => null, 'min_score' => null, 'max_score' => null, 'counts_in_gpa' => false],
    ];

    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('points', 3, 2)->nullable()->after('name');
            $table->decimal('min_score', 5, 2)->nullable()->after('points');
            $table->decimal('max_score', 5, 2)->nullable()->after('min_score');
            $table->boolean('counts_in_gpa')->default(true)->after('max_score');
        });

        if (! DB::table('grades')->exists()) {
            return;
        }

        $now = now();

        foreach ($this->scale as $index => $grade) {
            $values = $grade + ['order' => $index + 1, 'updated_at' => $now];

            if (DB::table('grades')->where('name', $grade['name'])->exists()) {
                DB::table('grades')->where('name', $grade['name'])->update($values);
            } else {
                DB::table('grades')->insert($values + ['is_pending_default' => false, 'created_at' => $now]);
            }
        }

        DB::table('grades')->where('is_pending_default', true)->update([
            'points' => null,
            'min_score' => null,
            'max_score' => null,
            'counts_in_gpa' => false,
            'order' => count($this->scale) + 1,
        ]);

        $failingGradeIds = DB::table('grades')->whereIn('name', ['F', 'FX'])->pluck('id');
        $alreadyFailing = DB::table('failing_grade_settings')->pluck('grade_id')->all();

        foreach ($failingGradeIds->diff($alreadyFailing) as $gradeId) {
            DB::table('failing_grade_settings')->insert(['grade_id' => $gradeId, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['points', 'min_score', 'max_score', 'counts_in_gpa']);
        });
    }
};
