<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">خصومات الطلاب</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="#">المالية</a></li>
                    <li class="breadcrumb-item active">الخصومات</li>
                </ol>
            </nav>
        </div>
        @can('discounts.create')
            <button class="btn btn-primary" wire:click="create">
                <i class="ti tabler-plus me-1"></i> منح خصم
            </button>
        @endcan
        <button class="btn {{ $showReport ? 'btn-secondary' : 'btn-label-primary' }}" wire:click="toggleReport">
            <i class="ti tabler-report me-1"></i> تقرير الخصومات
        </button>
    </div>

    <div class="alert alert-info d-flex gap-2">
        <i class="ti tabler-info-circle fs-4"></i>
        <div>
            الخصم يُمنح يدويًا موثَّقًا برقم قرار، ويُطبَّق تلقائيًا على حوافظ الرسوم المؤهلة وقت الإصدار.
            أما <strong>رصيد الهدية للمحفظة</strong> فليس خصمًا ويُمَنح من شاشة إدارة المحفظة.
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">البحث باسم الطالب أو الكود</label>
                    <input type="text" class="form-control" placeholder="كود أو اسم الطالب..." wire:model.live.debounce.400ms="search">
                </div>
                <div class="col-md-2">
                    <label class="form-label">الحالة</label>
                    <select class="form-select" wire:model.live="statusFilter">
                        <option value="">كل الحالات</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">النطاق</label>
                    <select class="form-select" wire:model.live="scopeFilter">
                        <option value="">كل النطاقات</option>
                        @foreach($scopes as $scope)
                            <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">السنة الدراسية</label>
                    <select class="form-select" wire:model.live="yearFilter">
                        <option value="">كل السنوات</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الترم</label>
                    <select class="form-select" wire:model.live="semesterFilter">
                        <option value="">كل الأتراب</option>
                        @foreach($semesters as $semester)
                            <option value="{{ $semester->value }}">{{ $semester->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if($showReport)
        <!-- Discounts report -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="ti tabler-report me-2"></i>تقرير الخصومات المطبَّقة</h5>
                <span class="text-success fw-bold">إجمالي الخصومات المطبَّقة: {{ number_format($reportTotal, 2) }} ج.م</span>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-2">
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" wire:model.live="reportFilters.from" title="من تاريخ">
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" wire:model.live="reportFilters.to" title="إلى تاريخ">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" wire:model.live="reportFilters.department_id">
                            <option value="">كل التخصصات</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" wire:model.live="reportFilters.level_id">
                            <option value="">كل الفرق</option>
                            @foreach($levels as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" placeholder="كود/اسم الطالب" wire:model.live.debounce.400ms="reportFilters.student_code">
                    </div>
                </div>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>النوع</th>
                            <th>المبلغ المطبَّق</th>
                            <th>السبب</th>
                            <th>رقم القرار</th>
                            <th>مانح الخصم</th>
                            <th>حافظة التطبيق</th>
                            <th>تاريخ التطبيق</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reportRows as $row)
                            <tr>
                                <td>{{ $row->discount?->student?->name }} <small class="text-muted">{{ $row->discount?->student?->username }}</small></td>
                                <td>{{ $row->discount?->mode?->label() }} ({{ $row->discount?->scope?->label() }})</td>
                                <td class="text-success fw-bold">{{ number_format((float) $row->applied_amount, 2) }} ج.م</td>
                                <td>{{ $row->discount?->reason }}</td>
                                <td>{{ $row->discount?->decision_number ?? '—' }}</td>
                                <td>{{ $row->discount?->creator?->name ?? '—' }}</td>
                                <td>{{ $row->ticket?->ticket_number }}</td>
                                <td>{{ $row->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">لا توجد نتائج</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($reportRows instanceof \Illuminate\Pagination\AbstractPaginator)
                <div class="card-footer">{{ $reportRows->links() }}</div>
            @endif
        </div>
    @endif

    @if($historyDiscountId !== null)
        <!-- Audit timeline -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ti tabler-timeline me-2"></i>سجل التطبيقات والتدقيق</h5>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($historyRows as $entry)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-label-primary me-2">{{ $entry['label'] }}</span>
                            <span class="text-muted small">{{ $entry['detail'] }}</span>
                        </div>
                        <div class="text-muted small">
                            {{ $entry['who'] ?? 'النظام' }} — {{ $entry['time']->format('Y-m-d H:i') }}
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-center text-muted py-3">لا توجد أحداث مسجلة</li>
                @endforelse
            </ul>
        </div>
    @endif

    @can('discounts.create')
        <!-- Bulk CSV import -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header border-bottom">
                <h5 class="card-title mb-0"><i class="ti tabler-upload me-2"></i>استيراد جماعي (CSV)</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    أعمدة الملف: [كود الطالب، نوع الرسوم، قيمة الخصم، سبب الخصم] — مبالغ ثابتة لترم العام الحالي فقط.
                    يُرفض الملف بالكامل عند أي سطر غير صالح (الكل أو لا شيء).
                </p>
                <div class="row g-2 align-items-center">
                    <div class="col-md-8">
                        <input type="file" class="form-control" accept=".csv,text/csv" wire:model="importFile">
                        @error('importFile') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary" wire:click="importCsv" wire:loading.attr="disabled">
                            <span wire:loading.remove><i class="ti tabler-import me-1"></i>استيراد</span>
                            <span wire:loading>جارٍ الاستيراد…</span>
                        </button>
                    </div>
                </div>
                @if(!empty($importErrors))
                    <div class="alert alert-danger mt-3 mb-0">
                        <div class="fw-bold mb-1">تم رفض الملف — الأخطاء:</div>
                        <ul class="mb-0 ps-3">
                            @foreach($importErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    @endcan

    <!-- Discounts table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>النطاق</th>
                        <th>القيمة</th>
                        <th>المطبَّق / المتبقي</th>
                        <th>السبب</th>
                        <th>رقم القرار</th>
                        <th>مانح الخصم</th>
                        <th>الحالة</th>
                        <th class="text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($discounts as $discount)
                        <tr>
                            <td>
                                <div class="fw-medium">{{ $discount->student?->name }}</div>
                                <small class="text-muted">{{ $discount->student?->username }}</small>
                            </td>
                            <td>
                                {{ $discount->scope->label() }}
                                @if($discount->scope === \App\Enums\DiscountScope::Additional && $discount->fee_id)
                                    <small class="text-muted d-block">#{{ $discount->fee_id }}</small>
                                @endif
                                @if($discount->year || $discount->semester)
                                    <small class="text-muted d-block">
                                        {{ $discount->year?->year }} {{ $discount->semester?->label() }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                @if($discount->mode === \App\Enums\DiscountMode::Percentage)
                                    {{ rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.') }}٪
                                @else
                                    {{ number_format((float) $discount->value, 2) }} ج.م
                                @endif
                            </td>
                            <td>
                                @if($discount->mode === \App\Enums\DiscountMode::Fixed)
                                    <span class="text-success">{{ number_format((float) $discount->value - (float) $discount->remaining_amount, 2) }}</span>
                                    /
                                    <span>{{ number_format((float) $discount->remaining_amount, 2) }} ج.م</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $discount->reason }}</td>
                            <td>{{ $discount->decision_number ?? '—' }}</td>
                            <td>{{ $discount->creator?->name ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $discount->status->badgeClass() }}">{{ $discount->status->label() }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-label-secondary" title="سجل التطبيقات" wire:click="showHistory({{ $discount->id }})">
                                        <i class="ti tabler-timeline"></i>
                                    </button>
                                    @can('discounts.edit')
                                        @if($discount->isEditable())
                                            <button type="button" class="btn btn-sm btn-icon btn-label-primary" title="تعديل" wire:click="edit({{ $discount->id }})">
                                                <i class="ti tabler-pencil"></i>
                                            </button>
                                        @endif
                                        @foreach(($pendingCandidates[$discount->id] ?? collect()) as $candidate)
                                            <button type="button" class="btn btn-sm btn-label-success" title="تطبيق على الحافظة القائمة"
                                                    wire:click="applyToPendingTicket({{ $discount->id }}, {{ $candidate->id }})"
                                                    wire:confirm="سيتم إعادة تسعير الحافظة {{ $candidate->ticket_number }} وتسجيل التغيير. متابعة؟">
                                                <i class="ti tabler-receipt"></i> إعادة تسعير الحافظة
                                            </button>
                                        @endforeach
                                    @endcan
                                    @can('discounts.revoke')
                                        @if(in_array($discount->status, [\App\Enums\DiscountStatus::Active, \App\Enums\DiscountStatus::PartiallyApplied], true))
                                            <button type="button" class="btn btn-sm btn-icon btn-label-danger" title="إلغاء" wire:click="revokeTarget({{ $discount->id }})">
                                                <i class="ti tabler-ban"></i>
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">لا توجد خصومات مسجلة</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $discounts->links() }}
        </div>
    </div>

    <!-- Revoke modal -->
    @if($showRevokeModal)
        <div class="modal show d-block" style="background: rgba(0,0,0,.4);" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">إلغاء خصم</h5>
                        <button type="button" class="btn-close" wire:click="closeRevokeModal"></button>
                    </div>
                    <form wire:submit="revoke">
                        <div class="modal-body">
                            <div class="alert alert-warning small">
                                يُلغى المتبقي غير المستخدم فقط؛ التطبيقات المسجلة تبقى كأثر مالي. لا يمكن إلغاء خصم استُنفد بالكامل على حافظة مدفوعة — التصحيح يكون بتسوية يدوية موثقة.
                            </div>
                            <div class="mb-3">
                                <label class="form-label required">سبب الإلغاء</label>
                                <input type="text" class="form-control" wire:model="revokeForm.reason" placeholder="مثال: إلغاء بقرار الإدارة رقم ...">
                                @error('revokeForm.reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" wire:click="closeRevokeModal">تراجع</button>
                            <button type="submit" class="btn btn-danger">تأكيد الإلغاء</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Create modal -->
    @if($showModal)
        <div class="modal show d-block" style="background: rgba(0,0,0,.4);" id="discount-create-modal" data-bs-backdrop="static" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'تعديل الخصم' : 'منح خصم جديد' }}</h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <form wire:submit="save">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label required">الطالب (كود أو اسم)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" wire:model="studentLookup" placeholder="كود الطالب أو اسمه...">
                                    <button type="button" class="btn btn-label-primary" wire:click="lookupStudent">بحث</button>
                                </div>
                                @if($resolvedStudent)
                                    <small class="text-success">تم اختيار: {{ $resolvedStudent }}</small>
                                @endif
                                @error('form.student_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">النطاق</label>
                                    <select class="form-select" wire:model.live="form.scope">
                                        @foreach($scopes as $scope)
                                            <option value="{{ $scope->value }}">{{ $scope->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.scope') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                @if(($form['scope'] ?? '') === \App\Enums\DiscountScope::Additional->value)
                                    <div class="col-md-6">
                                        <label class="form-label required">الرسم الإضافي</label>
                                        <select class="form-select" wire:model="form.fee_id">
                                            <option value="">— اختر —</option>
                                            @foreach($additionalFees as $fee)
                                                <option value="{{ $fee->id }}">{{ $fee->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('form.fee_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                                <div class="col-md-12">
                                    <label class="form-label">السنة الدراسية / الترم</label>
                                    <div class="form-control bg-label-secondary">
                                        {{ $years->firstWhere('id', $form['year_id'] ?? null)?->year ?? 'كل السنوات' }}
                                        — {{ \App\Enums\Semester::tryFrom((string) ($form['semester'] ?? ''))?->label() ?? 'كل الأتراب' }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">نوع القيمة</label>
                                    <select class="form-select" wire:model.live="form.mode">
                                        @foreach($modes as $mode)
                                            <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required">{{ ($form['mode'] ?? '') === \App\Enums\DiscountMode::Percentage->value ? 'النسبة (٪)' : 'المبلغ (ج.م)' }}</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control" wire:model="form.value">
                                    @error('form.value') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">سبب الخصم</label>
                                <input type="text" class="form-control" wire:model="form.reason" placeholder="مثال: شهادة تكريم — قرار الإدارة">
                                @error('form.reason') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">رقم القرار</label>
                                <input type="text" class="form-control" wire:model="form.decision_number" placeholder="رقم القرار الرسمي (اختياري)">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" wire:click="closeModal">إلغاء</button>
                            <button type="submit" class="btn btn-primary">حفظ الخصم</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
