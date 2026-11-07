<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">إجراءات نهاية الترم</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">إجراءات نهاية الترم</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4" x-data="{ open: {{ $blockers !== [] && $isPromotion ? 'true' : 'false' }} }">
        <div class="card-header d-flex justify-content-between align-items-center" role="button" @click="open = ! open">
            <h5 class="mb-0"><i class="ti tabler-settings me-1"></i> إعدادات الترقية والإنذارات</h5>
            <i class="ti" :class="open ? 'tabler-chevron-up' : 'tabler-chevron-down'"></i>
        </div>
        <div class="card-body" x-show="open" x-cloak>
            <form wire:submit="saveRules">
                <h6 class="fw-bold">بداية كل فرقة (الساعات المكتسبة)</h6>
                <div class="row g-3 mb-3">
                    @foreach($levels as $level)
                        <div class="col-md-2" wire:key="start-hours-{{ $level->id }}">
                            <label class="form-label" for="start-{{ $level->id }}">{{ $level->name }}</label>
                            @if($loop->first)
                                <input type="text" id="start-{{ $level->id }}" class="form-control" value="0 (بداية الدراسة)" disabled>
                            @else
                                <input type="number" min="1" id="start-{{ $level->id }}" wire:model="levelStartHours.{{ $level->id }}"
                                       class="form-control @error('levelStartHours.'.$level->id) is-invalid @enderror">
                                @error('levelStartHours.'.$level->id) <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @endif
                        </div>
                    @endforeach
                    <div class="col-md-2">
                        <label class="form-label" for="graduationHours">التخرج</label>
                        <input type="number" min="1" id="graduationHours" wire:model="graduationHours" class="form-control @error('graduationHours') is-invalid @enderror">
                        @error('graduationHours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <h6 class="fw-bold">الإنذارات والفصل</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="warningThreshold">حد الإنذار (المعدل التراكمي أقل من)</label>
                        <input type="number" step="0.01" min="0" max="4" id="warningThreshold" wire:model="warningThreshold" class="form-control @error('warningThreshold') is-invalid @enderror">
                        @error('warningThreshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="exemptHours">معفي من الإنذار من (ساعات مكتسبة)</label>
                        <input type="number" min="1" id="exemptHours" wire:model="exemptHours" class="form-control @error('exemptHours') is-invalid @enderror">
                        @error('exemptHours') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="dismissalWarnings">عدد الإنذارات اللي بتفصل</label>
                        <input type="number" min="1" id="dismissalWarnings" wire:model="dismissalWarnings" class="form-control @error('dismissalWarnings') is-invalid @enderror">
                        @error('dismissalWarnings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="maxYearsInLevel">أقصى سنين "من الخارج" في الفرقة</label>
                        <input type="number" min="1" id="maxYearsInLevel" wire:model="maxYearsInLevel" class="form-control @error('maxYearsInLevel') is-invalid @enderror">
                        @error('maxYearsInLevel') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                @can('term_closures.settings')
                    <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled"><i class="ti tabler-device-floppy me-1"></i> حفظ الإعدادات</button>
                @endcan
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="yearId" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="yearId" id="yearId" class="form-select @error('yearId') is-invalid @enderror">
                        <option value="">اختر السنة</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                    @error('yearId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="semester" class="form-label fw-bold">الترم</label>
                    <select wire:model.live="semester" id="semester" class="form-select @error('semester') is-invalid @enderror">
                        <option value="">اختر الترم</option>
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('semester')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex gap-2 justify-content-md-end">
                    <button type="button" class="btn btn-label-primary" wire:click="simulate" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="simulate"><i class="ti tabler-player-play me-1"></i> محاكاة</span>
                        <span wire:loading wire:target="simulate"><span class="spinner-border spinner-border-sm me-1"></span> جاري الحساب...</span>
                    </button>
                    @can('term_closures.execute')
                        <button type="button" class="btn btn-danger" wire:click="execute" wire:loading.attr="disabled"
                                @disabled($blockers !== [])
                                wire:confirm="سيتم تنفيذ إجراءات نهاية الترم على كل الطلاب الحاليين ({{ $isPromotion ? 'الترقية وتغيير حالة القيد والفصل والتخرج' : 'الإنذارات الأكاديمية والفصل' }}). يُفضل مراجعة المحاكاة أولاً. هل أنت متأكد؟">
                            <span wire:loading.remove wire:target="execute"><i class="ti tabler-checks me-1"></i> تنفيذ</span>
                            <span wire:loading wire:target="execute"><span class="spinner-border spinner-border-sm me-1"></span> جاري التنفيذ...</span>
                        </button>
                    @endcan
                </div>
            </div>

            <div class="alert alert-secondary mt-4 mb-0">
                @if($isPromotion)
                    <b>بعد الترم الصيفي:</b> الطالب بيترقى للفرقة اللي بعدها لو ساعاته المكتسبة وصلت لساعات بدايتها
                    (@foreach($levels->slice(1) as $level){{ $level->name }} من {{ $level->start_hours ?? '؟' }}@if(! $loop->last)، @endif @endforeach)،
                    ويتخرج من آخر فرقة عند {{ $rules['graduation_hours'] }} ساعة مع اجتياز التربية العسكرية.
                    غير كده حالة القيد بتتغير مستجد ← باقي ← من الخارج، والفصل بقواعد chs ("باقي" في الأولى، "من الخارج" في التانية، أو {{ $rules['max_years_in_level'] }} سنين في نفس الفرقة).
                    واللي معدله {{ $rules['threshold'] }} فأكتر أو ساعاته {{ $rules['exempt_hours'] }} فأكتر بتتلغي كل إنذاراته.
                @else
                    <b>بعد الترم الأول والثاني:</b> الطالب اللي معدله التراكمي (لحد آخر الترم ده) أقل من {{ $rules['threshold'] }} وساعاته المكتسبة أقل من {{ $rules['exempt_hours'] }} ياخد إنذار،
                    وغيره يتشال عنه إنذار. أول ترم للطالب مبيتحسبش، و{{ $rules['dismissal_warnings'] }} إنذارات = فصل.
                @endif
                الأعذار بتتقرا من صفحة <b>الأعذار ووقف القيد</b>: عذر عن الترم أو وقف قيد السنة بيعفي من الإنذار، ووقف القيد أو عذر عن الترمين بيعفي من الرسوب في الفرقة بعد الصيفي.
            </div>

            @if($blockers !== [])
                <div class="alert alert-warning mt-3 mb-0">
                    <ul class="mb-0">
                        @foreach($blockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if($rows !== null)
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">
                    @if($viewingClosure)
                        نتيجة تنفيذ {{ $viewingClosure->semester->label() }} {{ $viewingClosure->year?->year }}
                    @else
                        محاكاة (لم يتم حفظ أي شيء)
                    @endif
                    <span class="text-muted small">— {{ count($rows) }} طالب</span>
                </h5>
                <select wire:model.live="outcomeFilter" class="form-select w-auto">
                    <option value="">كل النتائج</option>
                    @foreach($outcomes as $outcome)
                        @if($counts->has($outcome->value))
                            <option value="{{ $outcome->value }}">{{ $outcome->label() }} ({{ $counts[$outcome->value] }})</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="card-body pb-0">
                <div class="d-flex flex-wrap gap-2">
                    @foreach($outcomes as $outcome)
                        @if($counts->has($outcome->value))
                            <span class="badge bg-label-{{ $outcome->color() }} p-2 px-3">{{ $outcome->label() }}: {{ $counts[$outcome->value] }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 mt-3">
                    <thead>
                        <tr>
                            <th>الكود</th>
                            <th>الاسم</th>
                            <th>الفرقة</th>
                            <th>CGPA</th>
                            <th>الساعات المكتسبة</th>
                            <th>النتيجة</th>
                            <th>الإنذارات</th>
                            <th>حالة القيد</th>
                            <th>ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visibleRows as $index => $row)
                            @php($outcome = \App\Enums\TermOutcome::from($row['outcome']))
                            <tr wire:key="term-row-{{ $index }}-{{ $row['code'] }}">
                                <td>
                                    @can('students.view')
                                        <a href="{{ route('students.show', $row['student_id']) }}" target="_blank" title="مراجعة درجات الطالب">{{ $row['code'] }}</a>
                                    @else
                                        {{ $row['code'] }}
                                    @endcan
                                </td>
                                <td>{{ $row['name'] }}</td>
                                <td>
                                    {{ $row['level'] ?? '—' }}
                                    @if($row['level_after'] !== $row['level'])
                                        ← <b>{{ $row['level_after'] }}</b>
                                    @endif
                                </td>
                                <td>{{ number_format($row['cgpa'], 2) }}</td>
                                <td>{{ $row['earned_hours'] }}</td>
                                <td><span class="badge bg-label-{{ $outcome->color() }}">{{ $outcome->label() }}</span></td>
                                <td>
                                    {{ $row['warnings_before'] }}
                                    @if($row['warnings_after'] !== $row['warnings_before'])
                                        ← <b>{{ $row['warnings_after'] }}</b>
                                    @endif
                                </td>
                                <td>
                                    {{ $row['study_status'] ?? '—' }}
                                    @if($row['study_status_after'] !== $row['study_status'])
                                        ← <b>{{ $row['study_status_after'] }}</b>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $row['note'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">لا يوجد طلاب.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">الترمات المنفذة</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>الترم</th>
                        <th>الإجراء</th>
                        <th>عدد الطلاب</th>
                        <th>نفذه</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($closures as $closure)
                        <tr wire:key="closure-{{ $closure->id }}">
                            <td>{{ $closure->semester->label() }} {{ $closure->year?->year }}</td>
                            <td>{{ $closure->isPromotion() ? 'الترقية' : 'الإنذارات' }}</td>
                            <td>{{ $closure->records_count }}</td>
                            <td>{{ $closure->closedBy?->name ?? '—' }}</td>
                            <td>{{ $closure->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-label-secondary" wire:click="showClosure({{ $closure->id }})">عرض</button>
                                @can('term_closures.revert')
                                    @if($loop->first)
                                        <button type="button" class="btn btn-sm btn-label-danger" wire:click="revert({{ $closure->id }})"
                                                wire:confirm="سيتم إرجاع الفرقة وحالة القيد وحالة الطالب والإنذارات لكل طلاب هذا الترم كما كانت قبل التنفيذ. أي تعديل يدوي حصل على الطلاب دول بعد التنفيذ هيترجع برضه. هل أنت متأكد؟">
                                            تراجع
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">لم يتم تنفيذ إجراءات أي ترم بعد.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
