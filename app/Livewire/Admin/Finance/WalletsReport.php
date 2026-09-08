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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletsReport extends Component
{
    use WithPagination;

    public const FEE_TYPES = [
        'registration' => 'مصاريف التسجيل',
        'additional' => 'رسوم إضافية',
        'military_education' => 'التربية العسكرية',
        'other' => 'أخرى',
    ];

    public string $searchStudent = '';

    public ?int $searchYear = null;

    public ?Semester $searchSemester = null;

    public ?int $searchDepartment = null;

    public ?int $searchLevel = null;

    public ?int $searchSection = null;

    public string $searchFeeType = '';

    public string $paymentStatus = '';

    public string $paidFrom = '';

    public string $paidTo = '';

    public string $dueFrom = '';

    public string $dueTo = '';

    public string $sortField = 'due_total';

    public string $sortDirection = 'desc';

    public int $perPage = 15;

    /** @var array<int, int> */
    public array $expanded = [];

    public function updating(string $property): void
    {
        if (in_array($property, [
            'searchStudent', 'searchYear', 'searchSemester', 'searchDepartment',
            'searchLevel', 'searchSection', 'searchFeeType', 'paymentStatus',
            'paidFrom', 'paidTo', 'dueFrom', 'dueTo', 'perPage',
        ], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset([
            'searchStudent', 'searchYear', 'searchSemester', 'searchDepartment',
            'searchLevel', 'searchSection', 'searchFeeType', 'paymentStatus',
            'paidFrom', 'paidTo', 'dueFrom', 'dueTo',
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

    public function toggleDetails(int $studentId): void
    {
        $key = array_search($studentId, $this->expanded, true);

        if ($key !== false) {
            unset($this->expanded[$key]);
            $this->expanded = array_values($this->expanded);

            return;
        }

        $this->expanded[] = $studentId;
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $rows = $this->reportQuery()
            ->with(['level', 'section.department', 'wallet'])
            ->orderBy(...$this->orderByPair())
            ->get();

        $filename = 'wallets-report-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'اسم الطالب', 'كود الطالب', 'الرقم القومي', 'القسم', 'الفرقة', 'الشعبة',
                'عدد الحافظات', 'المستحق الأصلي', 'إجمالي الخصومات', 'صافي المستحق',
                'المدفوع', 'المتبقي', 'رصيد المحفظة', 'حالة السداد',
            ]);

            foreach ($rows as $student) {
                fputcsv($handle, [
                    $student->name,
                    $student->username,
                    $student->national_id,
                    $student->section?->department?->name ?? '—',
                    $student->level?->name ?? '—',
                    $student->section?->name ?? '—',
                    (int) $student->tickets_count,
                    number_format((float) $student->gross_total, 2, '.', ''),
                    number_format((float) $student->discount_total, 2, '.', ''),
                    number_format((float) $student->net_total, 2, '.', ''),
                    number_format((float) $student->paid_total, 2, '.', ''),
                    number_format((float) $student->due_total, 2, '.', ''),
                    number_format((float) ($student->wallet?->balance ?? 0), 2, '.', ''),
                    $this->paymentStatusLabel($student),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Tickets considered by the report (all years/semesters/types unless filtered).
     */
    protected function ticketScopeQuery(): Builder
    {
        return StudentFeeTicket::query()
            ->where('status', '!=', 'cancelled')
            ->when($this->searchYear, fn (Builder $q) => $q->where('year_id', $this->searchYear))
            ->when($this->searchSemester, fn (Builder $q) => $q->where('semester', $this->searchSemester->value))
            ->when($this->searchFeeType !== '', fn (Builder $q) => $q->where('fee_type', $this->searchFeeType));
    }

    /**
     * @return Builder<int, Student>
     */
    protected function reportQuery(): Builder
    {
        $aggregates = $this->ticketScopeQuery()
            ->select('student_id')
            ->selectRaw('COUNT(*) as tickets_count')
            ->selectRaw('SUM(COALESCE(original_amount, amount)) as gross_total')
            ->selectRaw('SUM(discount_amount) as discount_total')
            ->selectRaw('SUM(amount) as net_total')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_total")
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as due_total")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as due_count")
            ->groupBy('student_id');

        return Student::query()
            ->select([
                'students.*',
                'agg.tickets_count',
                'agg.gross_total',
                'agg.discount_total',
                'agg.net_total',
                'agg.paid_total',
                'agg.paid_count',
                'agg.due_total',
                'agg.due_count',
            ])
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
            ->when($this->paymentStatus === 'paid', fn (Builder $q) => $q->where('agg.due_count', 0)->where('agg.paid_count', '>', 0))
            ->when($this->paymentStatus === 'unpaid', fn (Builder $q) => $q->where('agg.paid_count', 0)->where('agg.due_count', '>', 0))
            ->when($this->paymentStatus === 'partial', fn (Builder $q) => $q->where('agg.paid_count', '>', 0)->where('agg.due_count', '>', 0))
            ->when($this->paidFrom !== '', fn (Builder $q) => $q->whereRaw('agg.paid_total >= CAST(? AS NUMERIC)', [(float) $this->paidFrom]))
            ->when($this->paidTo !== '', fn (Builder $q) => $q->whereRaw('agg.paid_total <= CAST(? AS NUMERIC)', [(float) $this->paidTo]))
            ->when($this->dueFrom !== '', fn (Builder $q) => $q->whereRaw('agg.due_total >= CAST(? AS NUMERIC)', [(float) $this->dueFrom]))
            ->when($this->dueTo !== '', fn (Builder $q) => $q->whereRaw('agg.due_total <= CAST(? AS NUMERIC)', [(float) $this->dueTo]));
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function orderByPair(): array
    {
        $column = match ($this->sortField) {
            'gross_total', 'discount_total', 'net_total', 'paid_total', 'due_total', 'tickets_count' => 'agg.'.$this->sortField,
            default => 'students.'.$this->sortField,
        };

        return [$column, $this->sortDirection];
    }

    public function paymentStatusLabel(Student $student): string
    {
        return match (true) {
            (float) $student->due_total <= 0 && (float) $student->paid_total > 0 => 'مسدد بالكامل',
            (float) $student->paid_total <= 0 && (float) $student->due_total > 0 => 'غير مسدد',
            (float) $student->paid_total > 0 && (float) $student->due_total > 0 => 'مسدد جزئياً',
            default => 'بدون مدفوعات',
        };
    }

    /**
     * @return Collection<int, Collection<int, StudentFeeTicket>>
     */
    protected function expandedTickets(): Collection
    {
        if ($this->expanded === []) {
            return collect();
        }

        return $this->ticketScopeQuery()
            ->whereIn('student_id', $this->expanded)
            ->orderBy('created_at')
            ->get()
            ->groupBy('student_id');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('finance.view'), 403);

        $query = $this->reportQuery();

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
                DB::raw('COALESCE(SUM(agg.tickets_count), 0) as tickets_count'),
                DB::raw('COALESCE(SUM(agg.gross_total), 0) as gross_total'),
                DB::raw('COALESCE(SUM(agg.discount_total), 0) as discount_total'),
                DB::raw('COALESCE(SUM(agg.net_total), 0) as net_total'),
                DB::raw('COALESCE(SUM(agg.paid_total), 0) as paid_total'),
                DB::raw('COALESCE(SUM(agg.due_total), 0) as due_total'),
            ])
            ->first();

        return view('livewire.admin.finance.wallets-report', [
            'students' => $students,
            'totals' => $totals,
            'years' => Year::query()->latest()->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'levels' => Level::query()->orderBy('name')->get(),
            'sections' => Section::query()->orderBy('name')->get(),
            'ticketsByStudent' => $this->expandedTickets(),
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
