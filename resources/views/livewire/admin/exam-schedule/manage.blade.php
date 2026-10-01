<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">{{ $committee->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('exam-schedules.index', ['year' => $committee->year_id, 'semester' => $committee->semester->value]) }}">جدول الامتحانات</a></li>
                    <li class="breadcrumb-item active">{{ $committee->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @can('exam_schedules.edit')
                <a class="btn btn-label-info" href="{{ route('exam-schedules.edit', $committee) }}">
                    <i class="ti tabler-edit me-1"></i> بيانات اللجنة
                </a>
            @endcan
            <a class="btn btn-label-secondary" target="_blank" href="{{ route('exam-schedules.print.committee', $committee) }}">
                <i class="ti tabler-printer me-1"></i> طباعة
            </a>
            @can('exam_schedules.publish')
                @if($committee->isPublished())
                    <button type="button" class="btn btn-label-warning"
                            onclick="confirmAction('إخفاء الجدول', 'سيُخفى جدول اللجنة عن الطلاب ويعود لمسودة. هل أنت متأكد؟', () => @this.call('unpublish'))">
                        إخفاء عن الطلاب
                    </button>
                @else
                    <button type="button" class="btn btn-success"
                            onclick="confirmAction('نشر الجدول', 'سيظهر جدول اللجنة لطلابها فور النشر. هل أنت متأكد؟', () => @this.call('publish'))">
                        نشر
                    </button>
                @endif
            @endcan
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-4">
            <span>السنة: <b>{{ $committee->year?->year }}</b></span>
            <span>الترم: <b>{{ $committee->semester->label() }}</b></span>
            <span>المكان: <b>{{ $committee->venue?->name }}</b></span>
            <span>الطلاب: <b class="{{ $chips->count() >= $committee->capacity ? 'text-danger' : '' }}">{{ $chips->count() }} / {{ $committee->capacity }}</b></span>
            <span class="badge {{ $committee->status->badgeClass() }}">{{ $committee->status->label() }}</span>
            @if($window)
                <span class="text-muted small ms-auto">فترة الامتحانات: {{ $window['from'] }} → {{ $window['to'] }}</span>
            @endif
        </div>
    </div>

    {{-- Students --}}
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">طلاب اللجنة</h5>
            <span class="text-muted small">اكتب كود الطالب واضغط Enter أو مسافة — ويمكن لصق مجموعة أكواد مرة واحدة</span>
        </div>
        <div class="card-body">
            <div class="form-control d-flex flex-wrap align-items-center gap-1 p-2"
                 style="min-height: 48px; max-height: 260px; overflow-y: auto; cursor: text;"
                 x-data
                 x-on:click="$refs.codeInput?.focus()">
                @foreach($chips as $chip)
                    <span class="badge bg-label-primary d-inline-flex align-items-center gap-1 py-2" title="{{ $chip->student?->name }} — رقم الجلوس {{ $chip->seat_number }}" wire:key="chip-{{ $chip->id }}">
                        {{ $chip->student?->username }}
                        @can('exam_schedules.edit')
                            <button type="button" class="btn p-0 border-0 lh-1 text-primary" aria-label="حذف"
                                    wire:click.stop="removeStudent({{ $chip->student_id }})">
                                <i class="ti tabler-x" style="font-size: .85rem"></i>
                            </button>
                        @endcan
                    </span>
                @endforeach
                @can('exam_schedules.edit')
                    <input type="text"
                           x-ref="codeInput"
                           wire:model="codeInput"
                           wire:keydown.enter.prevent="addCodes"
                           x-on:keydown.space.prevent="$wire.addCodes()"
                           x-on:paste="setTimeout(() => $wire.addCodes(), 0)"
                           class="border-0 flex-grow-1 bg-transparent"
                           style="outline: none; min-width: 180px;"
                           placeholder="{{ $chips->isEmpty() ? 'كود الطالب ثم Enter…' : 'أضف كود…' }}"
                           dir="ltr">
                @endcan
            </div>

            @if($codeErrors !== [])
                <div class="alert alert-danger mt-3 mb-0 py-2">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <b>أكواد لم تُضف:</b>
                        <button type="button" class="btn btn-sm btn-text-danger p-0" wire:click="clearCodeErrors">مسح</button>
                    </div>
                    <ul class="mb-0 small">
                        @foreach($codeErrors as $code => $reason)
                            <li wire:key="code-error-{{ $code }}"><span dir="ltr">{{ $code }}</span> — {{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if($chips->isNotEmpty())
            <div class="card-body border-top pt-3 pb-0">
                <input type="text" wire:model.live.debounce.400ms="memberSearch" class="form-control form-control-sm w-auto" placeholder="بحث بالكود أو الاسم">
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>رقم الجلوس</th>
                            <th>كود الطالب</th>
                            <th>الاسم</th>
                            <th>الفرقة</th>
                            <th>الشعبة</th>
                            <th>امتحاناته في اللجنة</th>
                            @can('exam_schedules.edit')
                                <th class="text-end"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            @php($registered = $memberCourseIds[$member->student_id] ?? [])
                            @php($sitting = count(array_intersect($registered, $scheduledCourseIds)))
                            <tr wire:key="member-{{ $member->id }}">
                                <td class="fw-medium">{{ $member->seat_number }}</td>
                                <td dir="ltr" class="text-end">{{ $member->student?->username }}</td>
                                <td>{{ $member->student?->name }}</td>
                                <td>{{ $member->student?->level?->name ?? '—' }}</td>
                                <td>{{ $member->student?->section?->name ?? '—' }}</td>
                                <td>
                                    @if($registered === [])
                                        <span class="badge bg-label-warning">لا يوجد تسجيل معتمد</span>
                                    @else
                                        <span class="badge bg-label-{{ $sitting > 0 ? 'success' : 'secondary' }}">{{ $sitting }} / {{ count($registered) }}</span>
                                    @endif
                                </td>
                                @can('exam_schedules.edit')
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-label-danger" wire:click="removeStudent({{ $member->student_id }})">
                                            <i class="ti tabler-trash"></i>
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-center">
                {{ $members->links() }}
            </div>
        @endif
    </div>

    {{-- Exam timetable --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">مواعيد امتحانات اللجنة</h5>
        </div>

        @can('exam_schedules.edit')
            <div class="card-body border-bottom">
                @if($courses->isEmpty())
                    <p class="text-muted mb-0">أضف طلابًا لديهم تسجيل معتمد في هذا الترم أولًا لتظهر موادهم هنا.</p>
                @else
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="course_id">المادة</label>
                            <select id="course_id" wire:model="course_id" class="form-select @error('course_id') is-invalid @enderror">
                                <option value="">اختر المادة</option>
                                @foreach($courses as $course)
                                    <option value="{{ $course->id }}" wire:key="course-option-{{ $course->id }}">
                                        {{ $course->name }} ({{ $courseCounts[$course->id] ?? 0 }} طالب){{ in_array($course->id, $scheduledCourseIds, true) ? ' ✓' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('course_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="type">النوع</label>
                            <select id="type" wire:model="type" class="form-select @error('type') is-invalid @enderror">
                                @foreach($examTypes as $t)
                                    <option value="{{ $t->value }}">{{ $t->label() }}</option>
                                @endforeach
                            </select>
                            @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="exam_date">التاريخ</label>
                            <input type="date" id="exam_date" wire:model="exam_date"
                                   @if($window) min="{{ $window['from'] }}" max="{{ $window['to'] }}" @endif
                                   class="form-control @error('exam_date') is-invalid @enderror">
                            @error('exam_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="start_time">من</label>
                            <input type="time" step="900" id="start_time" wire:model="start_time" class="form-control @error('start_time') is-invalid @enderror">
                            @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="end_time">إلى</label>
                            <input type="time" step="900" id="end_time" wire:model="end_time" class="form-control @error('end_time') is-invalid @enderror">
                            @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="notes">ملاحظات</label>
                            <input type="text" id="notes" wire:model="notes" class="form-control @error('notes') is-invalid @enderror">
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="button" class="btn btn-primary flex-grow-1" wire:click="saveSession" wire:loading.attr="disabled" wire:target="saveSession">
                                <i class="ti tabler-{{ $editingSessionId ? 'device-floppy' : 'plus' }} me-1"></i>
                                {{ $editingSessionId ? 'حفظ التعديل' : 'إضافة الميعاد' }}
                            </button>
                            @if($editingSessionId)
                                <button type="button" class="btn btn-label-secondary" wire:click="cancelSessionEdit">إلغاء</button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endcan

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>المادة</th>
                        <th>النوع</th>
                        <th>اليوم</th>
                        <th>التاريخ</th>
                        <th>الميعاد</th>
                        <th>الممتحنون</th>
                        <th class="text-end">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}" class="{{ (int) $editingSessionId === $session->id ? 'table-active' : '' }}">
                            <td class="fw-medium">{{ $session->course->name }}</td>
                            <td>{{ $session->type->label() }}</td>
                            <td>{{ $session->exam_date->locale('ar')->isoFormat('dddd') }}</td>
                            <td>{{ $session->exam_date->format('Y-m-d') }}</td>
                            <td dir="ltr" class="text-end">{{ $session->timeRangeLabel() }}</td>
                            <td><span class="badge bg-label-primary">{{ $courseCounts[$session->course_id] ?? 0 }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-label-secondary" target="_blank" href="{{ route('exam-schedules.print.session', $session) }}" title="كشف حضور">
                                    <i class="ti tabler-printer"></i>
                                </a>
                                @can('exam_schedules.edit')
                                    <button type="button" class="btn btn-sm btn-label-info" wire:click="editSession({{ $session->id }})">تعديل</button>
                                    <button type="button" class="btn btn-sm btn-label-danger"
                                            onclick="confirmAction('حذف الميعاد', 'هل أنت متأكد من حذف ميعاد امتحان هذه المادة؟', () => @this.call('deleteSession', {{ $session->id }}))">
                                        حذف
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">لم تُحدد مواعيد امتحانات لهذه اللجنة بعد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
