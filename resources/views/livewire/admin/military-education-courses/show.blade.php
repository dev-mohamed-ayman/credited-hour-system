<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('military-education-courses.index') }}" class="btn btn-label-secondary btn-icon rounded-circle">
                <i class="ti tabler-arrow-right"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-heading">{{ $course->name }}</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('military-education-courses.index') }}">دورات التربية العسكرية</a></li>
                        <li class="breadcrumb-item active">{{ $course->name }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        @if($course->status?->value === 'active')
            <button type="button" class="btn btn-warning shadow-sm" onclick="confirmAction('إغلاق الدورة', 'هل أنت متأكد من إغلاق هذه الدورة؟', () => @this.call('closeCourse'))">
                <i class="ti tabler-lock me-1"></i> إغلاق الدورة
            </button>
        @endif
    </div>

    <!-- Course Info Card -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar avatar-lg bg-label-primary rounded-3">
                            <i class="ti tabler-users ti-2xl"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $course->enrollments->count() }} / {{ $course->capacity }}</h5>
                            <small class="text-muted">المسجلين / السعة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar avatar-lg bg-label-info rounded-3">
                            <i class="ti tabler-gender-bigender ti-2xl"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $course->gender == 'male' ? 'ذكر' : 'أنثى' }}</h5>
                            <small class="text-muted">النوع</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar avatar-lg bg-label-success rounded-3">
                            <i class="ti tabler-currency-dollar ti-2xl"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ number_format($course->fee_amount, 2) }}</h5>
                            <small class="text-muted">المصاريف</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar avatar-lg {{ $course->status?->value === 'active' ? 'bg-label-success' : 'bg-label-secondary' }} rounded-3">
                            <i class="ti tabler-status-change ti-2xl"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">{{ $course->status?->label() ?? 'غير محدد' }}</h5>
                            <small class="text-muted">الحالة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrollments Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
            <h5 class="card-title mb-0">الطلاب المسجلين</h5>
            <span class="badge bg-label-primary">{{ $course->enrollments->count() }} طالب</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>الطالب</th>
                        <th>السنة الدراسية</th>
                        <th>الترم</th>
                        <th class="text-center">الحالة</th>
                        @if($course->status?->value === 'active')
                            <th class="text-center">الإجراءات</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($course->enrollments as $enrollment)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar avatar-sm">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            {{ mb_substr($enrollment->student->name, 0, 1) }}
                                        </span>
                                    </div>
                                    <a href="{{ route('students.show', $enrollment->student) }}" class="text-heading fw-medium text-decoration-none hover-primary">{{ $enrollment->student->name }}</a>
                                </div>
                            </td>
                            <td>{{ $enrollment->year?->year ?? '-' }}</td>
                            <td>{{ $enrollment->semester?->label() ?? '-' }}</td>
                            <td class="text-center">
                                                    @php
                                                        $statusClass = match($enrollment->status) {
                                                            \App\Enums\MilitaryEducationEnrollmentStatus::REGISTERED => 'bg-label-info',
                                                            \App\Enums\MilitaryEducationEnrollmentStatus::PASSED => 'bg-label-success',
                                                            \App\Enums\MilitaryEducationEnrollmentStatus::FAILED => 'bg-label-danger',
                                                            default => 'bg-label-secondary'
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $statusClass }}">{{ $enrollment->status?->label() ?? 'غير محدد' }}</span>
                                                </td>
                            @if($course->status == \App\Enums\MilitaryEducationCourseStatus::ACTIVE)
                                <td class="text-center">
                                    <div class="btn-group" role="group">
                                        <button type="button" wire:click="updateEnrollmentStatus({{ $enrollment->id }}, 'passed')" class="btn btn-sm btn-success">ناجح</button>
                                        <button type="button" wire:click="updateEnrollmentStatus({{ $enrollment->id }}, 'failed')" class="btn btn-sm btn-danger">راسب</button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $course->status?->value === 'active' ? 5 : 4 }}" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti tabler-users-minus d-block mb-2 fs-1"></i>
                                    لا يوجد طلاب مسجلين في هذه الدورة
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
