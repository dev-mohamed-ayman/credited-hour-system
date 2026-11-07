<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">شؤون الطلاب /</span> الأعذار ووقف القيد
    </h4>

    <div class="card mb-4">
        <div class="card-body">
            <form wire:submit="search" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="studentCode">كود الطالب</label>
                    <input type="text" id="studentCode" wire:model="studentCode" class="form-control @error('studentCode') is-invalid @enderror" placeholder="مثال: CS250001">
                    @error('studentCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ti tabler-search me-1"></i> بحث</button>
                </div>
            </form>

            <div class="alert alert-secondary mt-4 mb-0 small">
                <b>عذر عن مادة:</b> المواد المختارة تبقى IC. <b>عذر عن ترم:</b> كل مواد الترم تبقى IC، والترم ميتحسبش في الإنذارات.
                <b>وقف قيد:</b> في الترم الأول بس، ويشمل السنة كلها (لا إنذار ولا رسوب في الفرقة). ولو الطالب عنده عذر عن الترمين، ميتحسبش عليه رسوب في الفرقة.
                مينفعش عذر لمادة ليها نتيجة، ولا عذر في الصيفي، ولا بعد تنفيذ إجراءات نهاية الترم.
            </div>
        </div>
    </div>

    @if($student)
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                <h5 class="mb-0">{{ $student->name }} <span class="text-muted small">({{ $student->username }})</span></h5>
                <div class="small text-muted">
                    {{ $student->section?->department?->name }} — {{ $student->level?->name }} — الحالة: {{ $student->status?->label() ?? '—' }}
                </div>
            </div>

            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>السنة</th>
                            <th>الترم</th>
                            <th>النوع</th>
                            <th>السبب</th>
                            <th>المواد</th>
                            <th>سجله</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($excuses as $excuse)
                            <tr wire:key="excuse-{{ $excuse->id }}">
                                <td>{{ $excuse->year?->year }}</td>
                                <td>{{ $excuse->type === \App\Enums\Student\ExcuseType::SUSPENSION ? 'السنة كلها' : $excuse->semester->label() }}</td>
                                <td><span class="badge bg-label-{{ $excuse->type === \App\Enums\Student\ExcuseType::SUSPENSION ? 'warning' : 'info' }}">{{ $excuse->type->label() }}</span></td>
                                <td>{{ $excuse->reason }}</td>
                                <td>{{ count($excuse->excused_course_ids ?? []) }}</td>
                                <td>{{ $excuse->createdBy?->name ?? '—' }}</td>
                                <td class="text-end">
                                    @can('student_excuses.delete')
                                        <button type="button" class="btn btn-sm btn-label-danger" wire:click="remove({{ $excuse->id }})"
                                                wire:confirm="سيتم حذف العذر ورجوع مواده Pending. هل أنت متأكد؟">حذف</button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">لا توجد أعذار مسجلة لهذا الطالب.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @can('student_excuses.create')
            <div class="card">
                <h5 class="card-header">تسجيل عذر</h5>
                <div class="card-body">
                    <form wire:submit="add" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="yearId">السنة الدراسية</label>
                            <select id="yearId" wire:model.live="yearId" class="form-select @error('yearId') is-invalid @enderror">
                                <option value="">اختر السنة</option>
                                @foreach($years as $year)
                                    <option value="{{ $year->id }}">{{ $year->year }}</option>
                                @endforeach
                            </select>
                            @error('yearId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="semester">الترم</label>
                            <select id="semester" wire:model.live="semester" class="form-select @error('semester') is-invalid @enderror">
                                <option value="">اختر الترم</option>
                                @foreach($semesters as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="type">نوع العذر</label>
                            <select id="type" wire:model.live="type" class="form-select @error('type') is-invalid @enderror">
                                <option value="">اختر النوع</option>
                                @foreach($types as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-12">
                            <label class="form-label" for="reason">السبب</label>
                            <input type="text" id="reason" wire:model="reason" class="form-control @error('reason') is-invalid @enderror" placeholder="مثال: عذر مرضي">
                            @error('reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-12">
                            <label class="form-label d-block">مواد الطالب في الترم</label>
                            @forelse($termCourses as $enrollment)
                                @php($hasResult = $enrollment->grade && ! $enrollment->grade->is_pending_default)
                                <div class="form-check" wire:key="term-course-{{ $enrollment->id }}">
                                    @if($type === \App\Enums\Student\ExcuseType::COURSE->value)
                                        <input class="form-check-input" type="checkbox" id="course-{{ $enrollment->id }}"
                                               value="{{ $enrollment->id }}" wire:model="courseIds" @disabled($hasResult)>
                                    @endif
                                    <label class="form-check-label" for="course-{{ $enrollment->id }}">
                                        {{ $enrollment->course?->code }} — {{ $enrollment->course?->name }}
                                        <span class="badge bg-label-{{ $hasResult ? 'secondary' : 'warning' }}">{{ $enrollment->grade?->name ?? 'Pending' }}</span>
                                    </label>
                                </div>
                            @empty
                                <span class="text-muted small">الطالب مش مسجل مواد في الترم ده.</span>
                            @endforelse
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"
                                    wire:confirm="سيتم تسجيل العذر وتحويل المواد المعنية إلى IC. هل أنت متأكد؟">
                                <i class="ti tabler-device-floppy me-1"></i> تسجيل العذر
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
</div>
