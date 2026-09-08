<?php

namespace App\Console\Commands;

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Models\AdditionalFee;
use App\Models\Student;
use App\Models\Year;
use App\Services\DiscountService;
use App\Support\CourseSemesterMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyDiscounts extends Command
{
    protected $signature = 'discounts:import-legacy';

    protected $description = 'استيراد الخصومات القديمة غير المستهلكة من جدول students_discounts إلى الكيان الجديد (محفظة/خدمات تعليمية تُستبعد)';

    /**
     * Legacy `type` value → new discount scope. Wallet-gift ('محفظة') and the
     * dead 'خدمات تعليمية' branch are deliberately never imported as discounts.
     */
    private const TYPE_TO_SCOPE = [
        'دراسية' => DiscountScope::Registration,
        'دراسات' => DiscountScope::Registration,
        'ادارية' => DiscountScope::Additional,
        'إدارية' => DiscountScope::Additional,
        'اخرى' => DiscountScope::Additional,
        'أخرى' => DiscountScope::Additional,
    ];

    private const SKIP_TYPES = ['محفظة', 'خدمات تعليمية'];

    public function handle(DiscountService $discounts): int
    {
        if (! DB::getSchemaBuilder()->hasTable('students_discounts')) {
            $this->warn('لا يوجد جدول خصومات قديمة (students_discounts) للاتصال الحالي.');

            return self::SUCCESS;
        }

        $adminFee = AdditionalFee::query()
            ->where(fn ($q) => $q->where('name', 'like', '%إدار%')->orWhere('name', 'like', '%ادار%'))
            ->orderBy('id')
            ->first();

        $rows = DB::table('students_discounts')->get();
        $imported = 0;
        $skipped = [];
        $unknownCodes = [];

        foreach ($rows as $row) {
            if (in_array($row->type, self::SKIP_TYPES, true)) {
                $skipped[$row->type] = ($skipped[$row->type] ?? 0) + 1;

                continue;
            }

            $scope = self::TYPE_TO_SCOPE[$row->type] ?? null;

            if ($scope === null) {
                $skipped[$row->type] = ($skipped[$row->type] ?? 0) + 1;

                continue;
            }

            $student = Student::where('username', $row->student_code)->first();

            if (! $student) {
                $unknownCodes[] = $row->student_code;

                continue;
            }

            $year = $row->year ? Year::where('year', $row->year)->first() : null;
            $semester = $row->semester ? CourseSemesterMapper::toEnum($row->semester) : null;

            $discounts->grant([
                'student_id' => $student->id,
                'scope' => $scope->value,
                'fee_id' => $scope === DiscountScope::Additional && $row->type === 'ادارية' && $adminFee ? $adminFee->id : null,
                'year_id' => $year?->id,
                'semester' => $semester?->value,
                'mode' => DiscountMode::Fixed->value,
                'value' => (string) $row->amount,
                'reason' => $row->reason ?: 'ترحيل من النظام القديم',
                'source' => 'legacy-migration',
            ]);

            $imported++;
        }

        $this->info("تم استيراد {$imported} خصمًا نشطًا من النظام القديم.");

        foreach ($skipped as $type => $count) {
            $this->line("تجاهُل: '{$type}' × {$count} — ".($type === 'محفظة' ? 'رصيد هدية يُمَر عبر WalletService::deposit لا كخصم' : 'كود ميت لا يُرحَّل'));
        }

        if ($unknownCodes !== []) {
            $this->warn('أكواد طلاب غير موجودة تم تخطيها: '.implode(', ', array_unique($unknownCodes)));
        }

        return self::SUCCESS;
    }
}
