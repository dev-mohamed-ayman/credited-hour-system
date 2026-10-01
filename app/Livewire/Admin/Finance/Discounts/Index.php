<?php

namespace App\Livewire\Admin\Finance\Discounts;

use App\Enums\DiscountMode;
use App\Enums\DiscountScope;
use App\Enums\DiscountStatus;
use App\Enums\Semester;
use App\Exceptions\DiscountEditException;
use App\Exceptions\DiscountInvalidException;
use App\Exceptions\DiscountRevokeException;
use App\Models\AdditionalFee;
use App\Models\Department;
use App\Models\Level;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\StudentDiscountUsage;
use App\Models\StudentFeeTicket;
use App\Models\Year;
use App\Services\DiscountService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';

    public ?string $statusFilter = null;

    public ?string $scopeFilter = null;

    public ?int $yearFilter = null;

    public ?string $semesterFilter = null;

    public bool $showModal = false;

    public bool $showRevokeModal = false;

    public ?int $editingId = null;

    public ?int $revokeTargetId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var array<string, mixed> */
    public array $revokeForm = [];

    public bool $showReport = false;

    public ?int $historyDiscountId = null;

    /** @var array<string, mixed> */
    public array $reportFilters = ['from' => '', 'to' => '', 'department_id' => '', 'level_id' => '', 'student_code' => ''];

    /** @var \Livewire\Features\SupportFileUploads\TemporaryFileUpload|string|null */
    public $importFile = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public function importCsv(): void
    {
        abort_unless(auth()->user()->can('discounts.create'), 403);

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'importFile.required' => 'برجاء اختيار ملف CSV أولاً',
            'importFile.mimes' => 'الملف يجب أن يكون بصيغة CSV',
        ]);

        $result = app(DiscountService::class)->importCsv(
            $this->importFile->getRealPath(),
            auth()->user()
        );

        $this->importErrors = $result['errors'];

        if ($result['success']) {
            $this->reset('importFile');
            $this->dispatch('toast', message: $result['message'], type: 'success');

            return;
        }

        $this->dispatch('toast', message: $result['message'], type: 'error');
    }

    public function toggleReport(): void
    {
        $this->showReport = ! $this->showReport;
        $this->historyDiscountId = null;
    }

    public function showHistory(int $discountId): void
    {
        $this->historyDiscountId = $this->historyDiscountId === $discountId ? null : $discountId;
    }

    public string $studentLookup = '';

    public ?string $resolvedStudent = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('discounts.view'), 403);

        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedScopeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSemesterFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        abort_unless(auth()->user()->can('discounts.create'), 403);

        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function edit(int $discountId): void
    {
        abort_unless(auth()->user()->can('discounts.edit'), 403);

        $discount = StudentDiscount::findOrFail($discountId);

        if (! $discount->isEditable()) {
            $this->dispatch('toast', message: 'لا يمكن تعديل خصم بعد تطبيقه — ألغه وأعد منحه', type: 'error');

            return;
        }

        $this->editingId = $discount->id;
        $this->form = [
            'student_id' => $discount->student_id,
            'scope' => $discount->scope->value,
            'fee_id' => $discount->fee_id,
            'year_id' => $discount->year_id,
            'semester' => $discount->semester?->value,
            'mode' => $discount->mode->value,
            'value' => (float) $discount->value,
            'reason' => $discount->reason,
            'decision_number' => $discount->decision_number,
        ];
        $this->resolvedStudent = $discount->student?->name.' — '.$discount->student?->username;
        $this->showModal = true;
    }

    public function revokeTarget(int $discountId): void
    {
        abort_unless(auth()->user()->can('discounts.revoke'), 403);

        $this->revokeTargetId = $discountId;
        $this->revokeForm = ['reason' => ''];
        $this->showRevokeModal = true;
    }

    public function applyToPendingTicket(int $discountId, int $ticketId): void
    {
        abort_unless(auth()->user()->can('discounts.edit'), 403);

        $discount = StudentDiscount::findOrFail($discountId);
        $ticket = StudentFeeTicket::findOrFail($ticketId);

        try {
            app(DiscountService::class)->applyToPendingTicket($discount, $ticket, auth()->user());
        } catch (DiscountEditException|DiscountInvalidException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->dispatch('toast', message: 'تمت إعادة تسعير الحافظة القائمة وتسجيل التغيير', type: 'success');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function closeRevokeModal(): void
    {
        $this->showRevokeModal = false;
    }

    public function lookupStudent(): void
    {
        $this->validate(['studentLookup' => 'required|string'], [
            'studentLookup.required' => 'برجاء إدخال كود أو اسم الطالب',
        ]);

        $student = Student::where('username', $this->studentLookup)
            ->orWhere('name', 'like', '%'.$this->studentLookup.'%')
            ->first();

        if (! $student) {
            $this->dispatch('toast', message: 'لم يتم العثور على طالب بهذا الكود أو الاسم', type: 'error');

            return;
        }

        $this->form['student_id'] = $student->id;
        $this->resolvedStudent = $student->name.' — '.$student->username;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $valueRules = ['required', 'numeric', 'min:0.01'];

        if (($this->form['mode'] ?? null) === DiscountMode::Percentage->value) {
            $valueRules[] = 'max:100';
        }

        return [
            'form.student_id' => ['required', 'integer', 'exists:students,id'],
            'form.scope' => ['required', Rule::enum(DiscountScope::class)],
            'form.fee_id' => [
                Rule::when(($this->form['scope'] ?? null) === DiscountScope::Additional->value, ['required', 'integer', 'exists:additional_fees,id']),
                'nullable',
                'integer',
                'exists:additional_fees,id',
            ],
            'form.year_id' => ['nullable', 'integer', 'exists:years,id'],
            'form.semester' => ['nullable', Rule::enum(Semester::class)],
            'form.mode' => ['required', Rule::enum(DiscountMode::class)],
            'form.value' => $valueRules,
            'form.reason' => ['required', 'string', 'max:255'],
            'form.decision_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'form.student_id.required' => 'برجاء اختيار الطالب',
            'form.student_id.exists' => 'الطالب المحدد غير موجود',
            'form.scope.required' => 'برجاء تحديد نطاق الخصم',
            'form.fee_id.required' => 'برجاء اختيار الرسم الإضافي المحدد لهذا النطاق',
            'form.fee_id.exists' => 'الرسم المحدد غير موجود',
            'form.mode.required' => 'برجاء تحديد نوع قيمة الخصم',
            'form.value.required' => 'برجاء إدخال قيمة الخصم',
            'form.value.min' => 'قيمة الخصم يجب أن تكون أكبر من صفر',
            'form.value.max' => 'النسبة المئوية لا يمكن أن تتجاوز 100٪',
            'form.reason.required' => 'سبب الخصم إلزامي للتوثيق',
        ];
    }

    public function save(): void
    {
        if ($this->editingId !== null) {
            $this->updateDiscount();

            return;
        }

        abort_unless(auth()->user()->can('discounts.create'), 403);

        $this->validate();

        try {
            app(DiscountService::class)->grant([
                'student_id' => (int) $this->form['student_id'],
                'scope' => $this->form['scope'],
                'fee_id' => ($this->form['scope'] ?? null) === DiscountScope::Additional->value ? ($this->form['fee_id'] ? (int) $this->form['fee_id'] : null) : null,
                'year_id' => Year::current()?->id,
                'semester' => Year::currentSemester()?->value,
                'mode' => $this->form['mode'],
                'value' => (string) $this->form['value'],
                'reason' => $this->form['reason'],
                'decision_number' => ! empty($this->form['decision_number']) ? $this->form['decision_number'] : null,
            ], auth()->user());
        } catch (DiscountInvalidException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'تم منح الخصم بنجاح', type: 'success');
    }

    public function updateDiscount(): void
    {
        abort_unless(auth()->user()->can('discounts.edit'), 403);

        $this->validate();

        $discount = StudentDiscount::findOrFail($this->editingId);

        try {
            app(DiscountService::class)->update($discount, [
                'scope' => $this->form['scope'],
                'fee_id' => ($this->form['scope'] ?? null) === DiscountScope::Additional->value ? ($this->form['fee_id'] ? (int) $this->form['fee_id'] : null) : null,
                'year_id' => $discount->year_id,
                'semester' => $discount->semester?->value,
                'mode' => $this->form['mode'],
                'value' => (string) $this->form['value'],
                'reason' => $this->form['reason'],
                'decision_number' => ! empty($this->form['decision_number']) ? $this->form['decision_number'] : null,
            ], auth()->user());
        } catch (DiscountEditException|DiscountInvalidException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->showModal = false;
        $this->dispatch('toast', message: 'تم تعديل الخصم بنجاح', type: 'success');
    }

    public function revoke(): void
    {
        abort_unless(auth()->user()->can('discounts.revoke'), 403);

        $this->validate([
            'revokeForm.reason' => 'required|string|max:255',
        ], [
            'revokeForm.reason.required' => 'سبب الإلغاء إلزامي للتوثيق',
        ]);

        $discount = StudentDiscount::findOrFail($this->revokeTargetId);

        try {
            app(DiscountService::class)->revoke($discount, $this->revokeForm['reason'], auth()->user());
        } catch (DiscountRevokeException $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'danger']);

            return;
        }

        $this->showRevokeModal = false;
        $this->dispatch('toast', message: 'تم إلغاء الخصم بنجاح', type: 'success');
    }

    /**
     * Applied-discount ledger rows filtered by period/department/level/student (FR-022).
     * Figures come from stored usage + ticket rows — never recomputed.
     */
    private function reportQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return StudentDiscountUsage::query()
            ->with(['discount.student:id,name,username', 'discount.creator:id,name', 'ticket:id,ticket_number,department_id,level_id,discount_amount,original_amount'])
            ->when($this->reportFilters['from'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '>=', $v))
            ->when($this->reportFilters['to'] ?? null, fn (Builder $q, string $v) => $q->whereDate('created_at', '<=', $v))
            ->when($this->reportFilters['department_id'] ?? null, fn (Builder $q, string $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('department_id', $v)))
            ->when($this->reportFilters['level_id'] ?? null, fn (Builder $q, string $v) => $q->whereHas('ticket', fn (Builder $t) => $t->where('level_id', $v)))
            ->when($this->reportFilters['student_code'] ?? null, function (Builder $q, string $v) {
                $q->whereHas('discount.student', function (Builder $s) use ($v) {
                    $s->where('username', 'like', '%'.$v.'%')->orWhere('name', 'like', '%'.$v.'%');
                });
            })
            ->latest();
    }

    /**
     * Unified audit timeline: events (grant/edit/revoke) + applications (FR-020).
     *
     * @return array<int, array{time: \Carbon\CarbonInterface, label: string, who: ?string, detail: string}>
     */
    private function historyRows(): array
    {
        if ($this->historyDiscountId === null) {
            return [];
        }

        $discount = StudentDiscount::with(['events.user:id,name', 'usages.ticket:id,ticket_number', 'usages.appliedBy:id,name'])->find($this->historyDiscountId);

        if (! $discount) {
            return [];
        }

        $entries = $discount->events->map(fn ($event) => [
            'time' => $event->created_at,
            'label' => $event->action->label(),
            'who' => $event->user?->name,
            'detail' => $event->meta['reason'] ?? ($event->meta['source'] ?? '—'),
        ]);

        $applications = $discount->usages->map(fn ($usage) => [
            'time' => $usage->created_at,
            'label' => 'تطبيق على حافظة',
            'who' => $usage->appliedBy?->name,
            'detail' => 'حافظة '.$usage->ticket?->ticket_number.' — '.number_format((float) $usage->applied_amount, 2).' ج.م',
        ]);

        return $entries->merge($applications)->sortByDesc('time')->values()->all();
    }

    private function resetForm(): void
    {
        $this->form = [
            'student_id' => null,
            'scope' => DiscountScope::Registration->value,
            'fee_id' => null,
            'year_id' => Year::current()?->id,
            'semester' => Year::currentSemester()?->value,
            'mode' => DiscountMode::Fixed->value,
            'value' => null,
            'reason' => null,
            'decision_number' => null,
        ];
        $this->studentLookup = '';
        $this->resolvedStudent = null;
    }

    /**
     * Pending in-scope tickets each discount could explicitly re-price (BR-7).
     *
     * @param  \Illuminate\Support\Collection<int, StudentDiscount>  $discounts
     * @return array<int, \Illuminate\Support\Collection<int, StudentFeeTicket>>
     */
    private function pendingCandidatesFor($discounts): array
    {
        $candidates = [];

        foreach ($discounts as $discount) {
            if (! in_array($discount->status, [DiscountStatus::Active, DiscountStatus::PartiallyApplied], true)) {
                continue;
            }

            $candidates[$discount->id] = StudentFeeTicket::query()
                ->unpaid()
                ->where('student_id', $discount->student_id)
                ->with('discountUsages')
                ->get()
                ->filter(fn (StudentFeeTicket $ticket) => $discount->appliesTo(
                    $ticket->fee_type,
                    $ticket->fee_id ? (int) $ticket->fee_id : null,
                    $ticket->year_id,
                    $ticket->semester,
                ) && ! $ticket->discountUsages->contains('student_discount_id', $discount->id))
                ->values();
        }

        return $candidates;
    }

    public function render()
    {
        abort_unless(auth()->user()->can('discounts.view'), 403);

        $discounts = StudentDiscount::query()
            ->with(['student:id,name,username', 'creator:id,name'])
            ->when($this->search, function (Builder $q) {
                $term = '%'.$this->search.'%';
                $q->whereHas('student', fn (Builder $s) => $s->where('name', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->when($this->statusFilter, fn (Builder $q, string $v) => $q->where('status', $v))
            ->when($this->scopeFilter, fn (Builder $q, string $v) => $q->where('scope', $v))
            ->when($this->yearFilter, fn (Builder $q, int $v) => $q->where('year_id', $v))
            ->when($this->semesterFilter, fn (Builder $q, string $v) => $q->where('semester', $v))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.finance.discounts.index', [
            'discounts' => $discounts,
            'pendingCandidates' => $this->pendingCandidatesFor($discounts->getCollection()),
            'reportRows' => $this->showReport ? $this->reportQuery()->paginate(20, ['*'], 'reportPage') : collect(),
            'reportTotal' => $this->showReport ? (float) $this->reportQuery()->sum('applied_amount') : 0.0,
            'historyRows' => $this->historyRows(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'levels' => Level::orderBy('name')->get(['id', 'name']),
            'scopes' => DiscountScope::cases(),
            'statuses' => DiscountStatus::cases(),
            'modes' => DiscountMode::cases(),
            'semesters' => Semester::cases(),
            'years' => Year::orderByDesc('id')->get(),
            'additionalFees' => AdditionalFee::orderBy('name')->get(['id', 'name']),
        ])
            ->extends('admin.layouts.app')
            ->section('content');
    }
}
