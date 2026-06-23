<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">تسجيل المواد الدراسية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">تسجيل المواد</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="searchCode" class="form-label fw-bold">كود الطالب</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model="searchCode" id="searchCode" class="form-control"
                               placeholder="أدخل كود الطالب" wire:keydown.enter="search">
                    </div>
                    @error('searchCode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="button" class="btn btn-primary" wire:click="search" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="search">بحث</span>
                        <span wire:loading wire:target="search">جاري البحث...</span>
                    </button>
                    @if($searched)
                        <button type="button" class="btn btn-label-secondary" wire:click="clear">مسح</button>
                    @endif
                </div>
            </div>

            @if($student)
                <div class="alert alert-primary d-flex align-items-center mt-4 mb-0" role="alert">
                    <i class="ti tabler-user me-2"></i>
                    <div>
                        <strong>{{ $student->name }}</strong>
                        <span class="mx-2">|</span>
                        الفرقة: {{ $student->level?->name ?? '—' }}
                        <span class="mx-2">|</span>
                        التخصص: {{ $student->section?->department?->name ?? '—' }}
                        <span class="mx-2">|</span>
                        الترم الحالي: {{ $currentSemester?->label() ?? '—' }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($searched && !$registrationAvailable)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ti tabler-alert-triangle me-2"></i>
            <span>لا يوجد تسجيل مواد في الترم الصيفي.</span>
        </div>
    @elseif($student && $registrationAvailable)
        @if($maxOptionalCourses !== null)
            <div class="alert alert-info py-2 mb-4">
                المواد الاختيارية المختارة:
                <strong>{{ $this->selectedOptionalCount }} من {{ $maxOptionalCourses }}</strong>
            </div>
        @endif

        <div class="row g-4">
            @foreach([
                'retake' => ['title' => 'مواد راسب فيها', 'courses' => $retakeCourses, 'model' => 'selectedRetake', 'color' => 'danger'],
                'improvement' => ['title' => 'مواد تحسين', 'courses' => $improvementCourses, 'model' => 'selectedImprovement', 'color' => 'warning'],
                'due' => ['title' => 'مواد الترم الحالي', 'courses' => $dueCourses, 'model' => 'selectedDue', 'color' => 'primary'],
            ] as $key => $section)
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-label-{{ $section['color'] }}">
                            <h5 class="mb-0">{{ $section['title'] }} ({{ $section['courses']->count() }})</h5>
                        </div>
                        <div class="card-body">
                            @forelse($section['courses'] as $course)
                                @php
                                    $wireModel = $section['model'];
                                    $isChecked = in_array($course->id, $this->{$wireModel});
                                    $isDisabled = $this->isOptionalDisabled($course->id);
                                @endphp
                                <div class="form-check mb-3 {{ $isDisabled ? 'opacity-50' : '' }}" wire:key="course-{{ $key }}-{{ $course->id }}">
                                    <input class="form-check-input" type="checkbox"
                                           wire:model.live="{{ $wireModel }}"
                                           value="{{ $course->id }}"
                                           id="course_{{ $key }}_{{ $course->id }}"
                                           @disabled($isDisabled)>
                                    <label class="form-check-label d-flex align-items-center gap-2 flex-wrap" for="course_{{ $key }}_{{ $course->id }}">
                                        <span class="fw-medium">{{ $course->name }}</span>
                                        <span class="badge bg-label-secondary">{{ $course->hours }} ساعة</span>
                                        @if($course->is_selected)
                                            <span class="badge bg-label-info">اختياري</span>
                                        @else
                                            <span class="badge bg-label-success">إجباري</span>
                                        @endif
                                    </label>
                                </div>
                            @empty
                                <p class="text-muted mb-0">لا توجد مواد متاحة في هذا القسم.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @can('course_registrations.create')
            <div class="mt-4 text-end">
                <button type="button" class="btn btn-primary btn-lg"
                        onclick="confirmAction('حفظ التسجيل', 'هل أنت متأكد من حفظ المواد المختارة؟', () => @this.call('save'))"
                        wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">
                        <i class="ti tabler-device-floppy me-1"></i> حفظ التسجيل
                    </span>
                    <span wire:loading wire:target="save">جاري الحفظ...</span>
                </button>
            </div>
        @endcan
    @endif
</div>
