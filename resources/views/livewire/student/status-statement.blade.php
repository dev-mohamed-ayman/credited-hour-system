<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('student.dashboard') }}" class="btn btn-label-secondary btn-icon rounded-circle">
                <i class="ti tabler-arrow-right"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-heading">بيان حالة الطالب</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item active">بيان الحالة</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>

    <!-- Student Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0"><i class="ti tabler-user me-2"></i>بيانات الطالب</h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="avatar avatar-xl">
                    <span class="avatar-initial rounded-circle bg-label-primary fs-2">
                        {{ mb_substr($student->name, 0, 1) }}
                    </span>
                </div>
                <div>
                    <h5 class="mb-1">{{ $student->name }}</h5>
                    <span class="badge bg-label-secondary">{{ $student->username }}</span>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 bg-label-primary rounded border-start border-primary border-4">
                        <small class="text-muted d-block">الفرقة</small>
                        <span class="fw-bold text-heading">{{ $student->level?->name ?? 'غير محدد' }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-label-info rounded border-start border-info border-4">
                        <small class="text-muted d-block">الشعبة</small>
                        <span class="fw-bold text-heading">{{ $student->section?->name ?? 'غير محدد' }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 bg-label-success rounded border-start border-success border-4">
                        <small class="text-muted d-block">القسم</small>
                        <span class="fw-bold text-heading">{{ $student->section?->department?->name ?? 'غير محدد' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registrations -->
    @if($student->registrations && $student->registrations->count())
        @php
            $yearGroups = $student->registrations->groupBy('year_id');
        @endphp
        <div id="academicRecordsAccordion">
            @foreach($yearGroups as $yearId => $yearRegistrations)
                @php
                    $year = $yearRegistrations->first()->year;
                @endphp
                <div class="card border-0 shadow-sm mb-3 overflow-hidden">
                    <div class="card-header border-bottom bg-transparent d-flex justify-content-between align-items-center p-0" id="headingYear{{ $yearId }}">
                        <button
                            class="accordion-button d-flex w-100 justify-content-between align-items-center p-4 text-start text-decoration-none"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#collapseYear{{ $yearId }}"
                            aria-expanded="{{ $selectedRegistration && in_array($selectedRegistration->id, $yearRegistrations->pluck('id')->toArray()) ? 'true' : 'false' }}"
                            aria-controls="collapseYear{{ $yearId }}"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div class="avatar avatar-md">
                                    <div class="avatar-initial bg-label-primary rounded-circle"><i class="ti tabler-calendar-star fs-4"></i></div>
                                </div>
                                <div>
                                    <span class="fw-bold text-heading fs-5">
                                        {{ $year?->year ?? 'سنة غير محددة' }}
                                    </span>
                                    <span class="badge bg-label-primary ms-2">
                                        {{ $yearRegistrations->count() }} ترم
                                    </span>
                                </div>
                            </div>
                            <div class="accordion-arrow-icon"></div>
                        </button>
                    </div>
                    <div
                        id="collapseYear{{ $yearId }}"
                        class="collapse {{ $selectedRegistration && in_array($selectedRegistration->id, $yearRegistrations->pluck('id')->toArray()) ? 'show' : '' }}"
                        aria-labelledby="headingYear{{ $yearId }}"
                    >
                        <div class="card-body p-4 border-top">
                            <div id="yearAccordion{{ $yearId }}">
                                @foreach($yearRegistrations as $reg)
                                    @php
                                        $gpaData = $this->calculateGPA($reg->courses);
                                        $semesterId = $reg->id;
                                    @endphp
                                    <div class="card border border-light shadow-sm mb-3">
                                        <div class="card-header border-bottom bg-transparent p-0" id="headingSem{{ $semesterId }}">
                                            <button
                                                class="accordion-button d-flex w-100 justify-content-between align-items-center p-3 text-start text-decoration-none"
                                                type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#collapseSem{{ $semesterId }}"
                                                aria-expanded="{{ $selectedRegistration && $selectedRegistration->id === $reg->id ? 'true' : 'false' }}"
                                                aria-controls="collapseSem{{ $semesterId }}"
                                            >
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar avatar-sm">
                                                        <div class="avatar-initial bg-label-info rounded-circle"><i class="ti tabler-calendar-time"></i></div>
                                                    </div>
                                                    <span class="fw-medium text-heading">
                                                        {{ $reg->semester?->label() ?? 'ترم غير محدد' }}
                                                    </span>
                                                </div>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div>
                                                        @if($reg->status === \App\Enums\RegistrationStatus::PENDING)
                                                            <span class="badge bg-label-warning">{{ $reg->status->label() }}</span>
                                                        @elseif($reg->status === \App\Enums\RegistrationStatus::APPROVED)
                                                            <span class="badge bg-label-success">{{ $reg->status->label() }}</span>
                                                        @else
                                                            <span class="badge bg-label-danger">{{ $reg->status->label() }}</span>
                                                        @endif
                                                    </div>
                                                    <span class="text-muted small">
                                                        <i class="ti tabler-calculator me-1"></i>
                                                        المعدل: <span class="fw-bold text-primary">{{ $gpaData['gpa'] }}</span>
                                                    </span>
                                                    <span class="badge bg-label-success">
                                                        {{ $gpaData['earned_hours'] }}/{{ $gpaData['total_hours'] }} ساعة
                                                    </span>
                                                </div>
                                            </button>
                                        </div>
                                        <div
                                            id="collapseSem{{ $semesterId }}"
                                            class="collapse {{ $selectedRegistration && $selectedRegistration->id === $reg->id ? 'show' : '' }}"
                                            aria-labelledby="headingSem{{ $semesterId }}"
                                        >
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th class="py-3">كود المادة</th>
                                                                <th class="py-3">اسم المادة</th>
                                                                <th class="text-center py-3">عدد الساعات</th>
                                                                <th class="text-center py-3">التقدير</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="table-border-bottom-0">
                                                            @foreach($reg->courses as $courseReg)
                                                                @php
                                                                    $course = $courseReg->course;
                                                                    $grade = $courseReg->grade;
                                                                @endphp
                                                                <tr>
                                                                    <td>
                                                                        <span class="fw-medium">{{ $course?->code ?? '-' }}</span>
                                                                    </td>
                                                                    <td class="fw-medium text-heading">
                                                                        {{ $course?->name ?? 'مادة غير محددة' }}
                                                                    </td>
                                                                    <td class="text-center">
                                                                        {{ $course?->hours ?? '-' }} ساعة
                                                                    </td>
                                                                    <td class="text-center">
                                                                        @if($grade)
                                                                            @php
                                                                                $badgeClass = match(true) {
                                                                                    $grade->order >= 90 => 'bg-label-success',
                                                                                    $grade->order >= 75 => 'bg-label-info',
                                                                                    $grade->order >= 50 => 'bg-label-warning',
                                                                                    default => 'bg-label-danger',
                                                                                };
                                                                            @endphp
                                                                            <span class="badge {{ $badgeClass }}">
                                                                                {{ $grade->name }}
                                                                            </span>
                                                                        @else
                                                                            <span class="text-muted">-</span>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                                <div class="card-footer border-top p-4">
                                                    <div class="row g-4">
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <div class="text-muted small mb-1">المعدل الفصلي</div>
                                                                <div class="fw-bold text-primary fs-4">{{ $gpaData['gpa'] }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <div class="text-muted small mb-1">عدد الساعات المسجلة</div>
                                                                <div class="fw-bold text-info fs-4">{{ $gpaData['total_hours'] }} ساعة</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="text-center">
                                                                <div class="text-muted small mb-1">عدد الساعات المكتسبة</div>
                                                                <div class="fw-bold text-success fs-4">{{ $gpaData['earned_hours'] }} ساعة</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="text-muted">
                    <i class="ti tabler-notes-off d-block mb-3 fs-1"></i>
                    <h5 class="mb-2">لا توجد تسجيلات للطالب حتى الآن</h5>
                </div>
            </div>
        </div>
    @endif
</div>
