<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">تسجيل المواد الدراسية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">تسجيل المواد</li>
                </ol>
            </nav>
        </div>
    </div>

    @if($student)
        <div class="card mb-4">
            <div class="card-body">
                <div class="alert alert-primary d-flex align-items-center mb-0" role="alert">
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
            </div>
        </div>
    @endif

    @if(!$registrationAvailable)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ti tabler-alert-triangle me-2"></i>
            <span>لا يوجد تسجيل مواد في الترم الصيفي.</span>
        </div>
    @elseif($student)
        @include('partials.registration.outstanding-fees', ['tickets' => $this->outstandingTickets])

        <div class="d-flex flex-column flex-md-row justify-content-end align-items-md-center mb-3 gap-3">
            @if($maxOptionalCourses !== null)
                <div class="badge bg-label-info p-2 px-3 fs-6 d-flex align-items-center rounded-pill">
                    <i class="ti tabler-info-circle me-1"></i>
                    <span>المواد الاختيارية المختارة: <strong class="ms-1">{{ $this->selectedOptionalCount }} / {{ $maxOptionalCourses }}</strong></span>
                </div>
            @endif
        </div>

        <div class="row g-4">
            @foreach([
                'retake' => ['title' => 'مواد راسب فيها', 'courses' => $retakeCourses, 'model' => 'selectedRetake', 'color' => 'danger', 'icon' => 'ti tabler-reload'],
                'improvement' => ['title' => 'مواد تحسين', 'courses' => $improvementCourses, 'model' => 'selectedImprovement', 'color' => 'warning', 'icon' => 'ti tabler-trending-up'],
                'due' => ['title' => 'مواد الترم الحالي', 'courses' => $dueCourses, 'model' => 'selectedDue', 'color' => 'primary', 'icon' => 'ti tabler-book'],
            ] as $key => $section)
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-label-{{ $section['color'] }} d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 d-flex align-items-center">
                                <i class="{{ $section['icon'] }} me-2"></i>
                                {{ $section['title'] }}
                            </h5>
                            <span class="badge bg-white text-{{ $section['color'] }} fs-6">{{ $section['courses']->count() }}</span>
                        </div>
                        <div class="table-responsive text-nowrap">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">اختيار</th>
                                        <th>اسم المادة</th>
                                        <th class="text-center">الساعات</th>
                                        <th class="text-center">النوع</th>
                                    </tr>
                                </thead>
                                <tbody class="table-border-bottom-0">
                                    @forelse($section['courses'] as $course)
                                        @php
                                            $wireModel = $section['model'];
                                            $isDisabled = $this->isOptionalDisabled($course->id);
                                        @endphp
                                        <tr class="{{ $isDisabled ? 'opacity-50' : '' }}" wire:key="course-{{ $key }}-{{ $course->id }}">
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center m-0">
                                                    <input class="form-check-input" type="checkbox"
                                                           wire:model.live="{{ $wireModel }}"
                                                           value="{{ $course->id }}"
                                                           id="course_{{ $key }}_{{ $course->id }}"
                                                           @disabled($isDisabled)>
                                                </div>
                                            </td>
                                            <td>
                                                <label class="form-check-label fw-medium d-block w-100 cursor-pointer" for="course_{{ $key }}_{{ $course->id }}">
                                                    {{ $course->name }}
                                                </label>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-label-secondary">{{ $course->hours }} ساعة</span>
                                            </td>
                                            <td class="text-center">
                                                @if($course->is_selected)
                                                    <span class="badge bg-label-info">اختياري</span>
                                                @else
                                                    <span class="badge bg-label-success">إجباري</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <div class="d-flex flex-column align-items-center">
                                                    <i class="ti tabler-book-off d-block mb-2" style="font-size: 3rem;"></i>
                                                    لا توجد مواد متاحة في هذا القسم
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @include('partials.registration.cost-summary', ['quote' => $this->costQuote])

        <div class="mt-4 text-end">
            <button type="button" class="btn btn-primary btn-lg"
                    onclick="confirmAction('حفظ التسجيل', 'هل أنت متأكد من حفظ المواد المختارة؟ سيتم مراجعة التسجيل من قبل المرشد الأكاديمي أو الأدمن.', () => @this.call('save'))"
                    wire:loading.attr="disabled"
                    @disabled($this->hasOutstandingFees)>
                <span wire:loading.remove wire:target="save">
                    <i class="ti tabler-device-floppy me-1"></i> حفظ التسجيل
                </span>
                <span wire:loading wire:target="save">
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    جاري الحفظ...
                </span>
            </button>
        </div>
    @endif
</div>
