<?php

namespace App\Livewire\Admin\Finance;

use App\Enums\Semester;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeTicket;
use App\Models\Year;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPaymentsReview extends Component
{
    use WithPagination;

    public const PER_PAGES = [25, 50, 100, 500];

    public string $searchStudent = '';

    public ?int $searchYear = null;

    public ?Semester $searchSemester = null;

    public ?int $searchDepartment = null;

    public ?int $searchLevel = null;

    public ?int $searchSection = null;

    public string $searchStudyStatus = '';

    public string $searchStudentStatus = '';

    public string $studyRemaining = '';

    public string $otherRemaining = '';

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public int $perPage = 50;

    public function mount(): void
    {
        $this->searchYear = Year::current()?->id;
        $this->searchSemester = Year::current()?->getCurrentSemester();
    }

    public function updating(string $property): void
    {
        if (in_array($property, [
            'searchStudent', 'searchYear', 'searchSemester', 'searchDepartment', 'searchLevel',
            'searchSection', 'searchStudyStatus', 'searchStudentStatus', 'studyRemaining',
            'otherRemaining', 'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'searchStudent', 'searchDepartment', 'searchLevel', 'searchSection',
            'searchStudyStatus', 'searchStudentStatus', 'studyRemaining', 'otherRemaining',
        ]);
        $this->resetErrorBag();
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $rows = $this->reviewQuery()
            ->with(['level', 'section.department', 'wallet'])
            ->orderBy(...$this->orderByPair())
            ->limit(10000)
            ->get();

        $filename = 'students-review-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'اسم الطالب', 'كود الطالب', 'التصنيف', 'الحالة الدراسية', 'القسم', 'الفرقة', 'الشعبة',
                'الساعات المسجلة',
                'إجمالي المصاريف الدراسية', 'الخصومات الدراسية', 'المدفوع دراسياً', 'باقي المصاريف الدراسية',
                'إجمالي المصاريف الأخرى', 'الخصومات الأخرى', 'المدفوع الأخرى', 'باقي المصاريف الأخرى',
                'رصيد المحفظة', 'حالة السداد',
            ]);

            foreach ($rows as $student) {
                fputcsv($handle, [
                    $student->name,
                    $student->username,
                    $student->study_status?->label() ?? '—',
                    $student->status?->label() ?? '—',
                    $student->section?->department?->name ?? '—',
                    $student->level?->name ?? '—',
                    $student->section?->name ?? '—',
                    (int) $student->registered_hours,
                    number_format((float) $student->study_gross, 2, '.', ''),
                    number_format((float) $student->study_discount, 2, '.', ''),
                    number_format((float) $student->study_paid, 2, '.', ''),
                    number_format($this->remainingOf($student, 'study'), 2, '.', ''),
                    number_format((float) $student->other_gross, 2, '.', ''),
                    number_format((float) $student->other_discount, 2, '.', ''),
                    number_format((float) $student->other_paid, 2, '.', ''),
                    number_format($this->remainingOf($student, 'other'), 2, '.', ''),
                    number_format((float) ($student->wallet?->balance ?? 0), 2, '.', ''),
                    $this->paymentStatusLabel($student),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reviewQuery(): Builder
    {
        $aggregates = StudentFeeTicket::query()
            ->where('status', '!=', 'cancelled')
            ->when($this->searchYear, fn (Builder $q) => $q->where('year_id', $this->searchYear))
            ->when($this->searchSemester, fn (Builder $q) => $q->where('semester', $this->searchSemester->value))
            ->select('student_id')
            ->selectRaw("SUM(CASE WHEN fee_type = 'registration' THEN COALESCE(original_amount, amount) ELSE 0 END) as study_gross")
            ->selectRaw("SUM(CASE WHEN fee_type = 'registration' THEN discount_amount ELSE 0 END) as study_discount")
            ->selectRaw("SUM(CASE WHEN fee_type = 'registration' THEN amount ELSE 0 END) as study_net")
            ->selectRaw("SUM(CASE WHEN fee_type = 'registration' AND status = 'paid' THEN amount ELSE 0 END) as study_paid")
            ->selectRaw("SUM(CASE WHEN fee_type != 'registration' THEN COALESCE(original_amount, amount) ELSE 0 END) as other_gross")
            ->selectRaw("SUM(CASE WHEN fee_type != 'registration' THEN discount_amount ELSE 0 END) as other_discount")
            ->selectRaw("SUM(CASE WHEN fee_type != 'registration' THEN amount ELSE 0 END) as other_net")
            ->selectRaw("SUM(CASE WHEN fee_type != 'registration' AND status = 'paid' THEN amount ELSE 0 END) as other_paid")
            ->selectRaw('COUNT(*) as tickets_count')
            ->groupBy('student_id');

        $hoursSub = DB::table('registration_courses')
            ->join('registrations', 'registrations.id', '=', 'registration_courses.registration_id')
            ->join('courses', 'courses.id', '=', 'registration_courses.course_id')
            ->selectRaw('COALESCE(SUM(courses.hours), 0)')
            ->whereColumn('registrations.student_id', 'students.id')
            ->where('registrations.status', 'approved')
            ->when($this->searchYear, fn ($q) => $q->where('registrations.year_id', $this->searchYear))
            ->when($this->searchSemester, fn ($q) => $q->where('registrations.semester', $this->searchSemester->value));

        return Student::query()
            ->select([
                'students.*',
                'agg.tickets_count',
                'agg.study_gross',
                'agg.study_discount',
                'agg.study_net',
                'agg.study_paid',
                'agg.other_gross',
                'agg.other_discount',
                'agg.other_net',
                'agg.other_paid',
                DB::raw('(agg.study_net - agg.study_paid) as study_remaining'),
                DB::raw('(agg.other_net - agg.other_paid) as other_remaining'),
            ])
            ->addSelect(['registered_hours' => $hoursSub])
            ->joinSub($aggregates, 'agg', fn ($join) => $join->on('agg.student_id', '=', 'students.id'))
            ->when($this->searchStudent !== '', function (Builder $query) {
                $term = '%'.$this->searchStudent.'%';

                $query->where(function (Builder $q) use ($term) {
                    $q->where('students.name', 'like', $term)
                        ->orWhere('students.username', 'like', $term)
                        ->orWhere('students.national_id', 'like', $term);
                });
            })
            ->when($this->searchDepartment, fn (Builder $q) => $q->whereRelation('section', 'department_id', $this->searchDepartment))
            ->when($this->searchLevel, fn (Builder $q) => $q->where('students.level_id', $this->searchLevel))
            ->when($this->searchSection, fn (Builder $q) => $q->where('students.section_id', $this->searchSection))
            ->when($this->searchStudyStatus !== '', fn (Builder $q) => $q->where('students.study_status', $this->searchStudyStatus))
            ->when($this->searchStudentStatus !== '', fn (Builder $q) => $q->where('students.status', $this->searchStudentStatus))
            ->when($this->studyRemaining === 'paid', function (Builder $q) {
                $q->whereRaw('agg.study_gross > 0')->whereRaw('(agg.study_net - agg.study_paid) <= 0.005');
            })
            ->when($this->studyRemaining === 'unpaid', function (Builder $q) {
                $q->whereRaw('agg.study_gross > 0')->whereRaw('(agg.study_net - agg.study_paid) > 0.005');
            })
            ->when($this->otherRemaining === 'paid', function (Builder $q) {
                $q->whereRaw('agg.other_gross > 0')->whereRaw('(agg.other_net - agg.other_paid) <= 0.005');
            })
            ->when($this->otherRemaining === 'unpaid', function (Builder $q) {
                $q->whereRaw('agg.other_gross > 0')->whereRaw('(agg.other_net - agg.other_paid) > 0.005');
            });
    }

    /**
     * @return array{0: string|\Illuminate\Database\Query\Expression, 1: string}
     */
    protected function orderByPair(): array
    {
        $column = match ($this->sortField) {
            'study_remaining' => DB::raw('(agg.study_net - agg.study_paid)'),
            'other_remaining' => DB::raw('(agg.other_net - agg.other_paid)'),
            'registered_hours' => 'registered_hours',
            'tickets_count' => 'agg.tickets_count',
            default => 'students.'.$this->sortField,
        };

        return [$column, $this->sortDirection];
    }

    public function remainingOf(Student $student, string $bucket): float
    {
        return round((float) $student->{$bucket.'_net'} - (float) $student->{$bucket.'_paid'}, 2);
    }

    public function paymentStatusLabel(Student $student): string
    {
        $paid = (float) $student->study_paid + (float) $student->other_paid > 0;
        $due = $this->remainingOf($student, 'study') > 0.005 || $this->remainingOf($student, 'other') > 0.005;

        return match (true) {
            $paid && $due => 'مسدد جزئياً',
            $due => 'غير مسدد',
            $paid => 'مسدد بالكامل',
            default => 'بدون مدفوعات',
        };
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $query = $this->reviewQuery();

        $students = $query
            ->clone()
            ->with(['level', 'section.department', 'wallet'])
            ->orderBy(...$this->orderByPair())
            ->paginate($this->perPage);

        $totals = $query
            ->clone()
            ->toBase()
            ->select([
                DB::raw('COUNT(*) as students_count'),
                DB::raw('COALESCE(SUM(agg.study_gross), 0) as study_gross'),
                DB::raw('COALESCE(SUM(agg.study_discount), 0) as study_discount'),
                DB::raw('COALESCE(SUM(agg.study_paid), 0) as study_paid'),
                DB::raw('COALESCE(SUM(agg.study_net - agg.study_paid), 0) as study_remaining'),
                DB::raw('COALESCE(SUM(agg.other_gross), 0) as other_gross'),
                DB::raw('COALESCE(SUM(agg.other_discount), 0) as other_discount'),
                DB::raw('COALESCE(SUM(agg.other_paid), 0) as other_paid'),
                DB::raw('COALESCE(SUM(agg.other_net - agg.other_paid), 0) as other_remaining'),
            ])
            ->first();

        return view('livewire.admin.finance.student-payments-review', [
            'students' => $students,
            'totals' => $totals,
            'years' => Year::query()->latest()->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'levels' => Level::query()->orderBy('name')->get(),
            'sections' => Section::query()->orderBy('name')->get(),
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
