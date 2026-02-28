<div>
    <!-- Header with Actions -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('students.index') }}" class="btn btn-label-secondary btn-icon rounded-circle">
                <i class="ti tabler-arrow-right"></i>
            </a>
            <div>
                <h4 class="mb-0 fw-bold text-heading">تفاصيل ملف الطالب</h4>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('students.index') }}">قائمة الطلاب</a></li>
                        <li class="breadcrumb-item active">{{ $student->name }}</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('students.edit', $student) }}" class="btn btn-primary shadow-sm">
                <i class="ti tabler-edit me-1"></i> تعديل البيانات
            </a>
            <div class="dropdown">
                <button class="btn btn-label-secondary dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                    <i class="ti tabler-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="javascript:void(0);"><i class="ti tabler-printer me-2"></i> طباعة الملف</a></li>
                    <li><a class="dropdown-item" href="javascript:void(0);"><i class="ti tabler-download me-2"></i> تصريف PDF</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="javascript:void(0);"><i class="ti tabler-trash me-2"></i> حذف الطالب</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Sidebar: Profile Card -->
        <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
            <!-- User Card -->
            <div class="card mb-4 border-0 shadow-sm overflow-hidden">
                <div class="card-body pb-1">
                    <div class="user-avatar-section">
                        <div class="d-flex align-items-center flex-column">
                            <div class="position-relative mb-4">
                                @if($student->image)
                                    <img class="img-fluid rounded-3 shadow-sm" src="{{ asset('storage/' . $student->image) }}" height="120" width="120" alt="User avatar">
                                @else
                                    <div class="avatar avatar-xl">
                                        <span class="avatar-initial rounded-3 bg-label-primary fs-1 shadow-sm">
                                            {{ mb_substr($student->name, 0, 1) }}
                                        </span>
                                    </div>
                                @endif
                                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle">
                                    <span class="visually-hidden">Online</span>
                                </span>
                            </div>
                            <div class="user-info text-center">
                                <h5 class="mb-1 fw-bold">{{ $student->name }}</h5>
                                <span class="badge bg-label-secondary mb-2">{{ $student->username }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around flex-wrap my-4 py-3 border-top border-bottom">
                        <div class="d-flex align-items-center me-4 mt-3 gap-3">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-primary rounded shadow-sm">
                                    <i class="ti tabler-school ti-md"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-0">{{ $student->level?->name ?? 'N/A' }}</h5>
                                <small>الفرقة</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center mt-3 gap-3">
                            <div class="avatar">
                                <div class="avatar-initial bg-label-success rounded shadow-sm">
                                    <i class="ti tabler-award ti-md"></i>
                                </div>
                            </div>
                            <div>
                                <h5 class="mb-0">{{ $student->score ?? '0' }}</h5>
                                <small>المجموع</small>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 small text-uppercase text-muted fw-bold">تفاصيل سريعة</p>
                    <div class="info-container">
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2">
                                <span class="fw-medium me-1">الحالة:</span>
                                @php
                                    $statusClass = match($student->status?->value) {
                                        'active' => 'bg-label-success',
                                        'inactive' => 'bg-label-danger',
                                        'suspended' => 'bg-label-warning',
                                        default => 'bg-label-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $student->status?->label() ?? 'غير محدد' }}</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">البريد:</span>
                                <span>{{ $student->email ?? 'لا يوجد' }}</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">الهاتف:</span>
                                <span class="text-primary">{{ $student->phone ?? 'لا يوجد' }}</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">الشعبة:</span>
                                <span>{{ $student->section?->name ?? 'لا يوجد' }}</span>
                            </li>
                            <li class="mb-2">
                                <span class="fw-medium me-1">الجنسية:</span>
                                <span>{{ $student->nationality?->name ?? 'غير مسجل' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Notes Card if exists -->
            @if($student->status_notes)
                <div class="card border-0 shadow-sm bg-label-warning">
                    <div class="card-body">
                        <h6 class="card-title fw-bold mb-2"><i class="ti tabler-info-circle me-1"></i> ملاحظات إضافية</h6>
                        <p class="card-text small mb-0">{{ $student->status_notes }}</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Main Content: Tabs -->
        <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
            <!-- Nav tabs -->
            <ul class="nav nav-pills flex-column flex-md-row mb-4 gap-2 gap-md-0" id="studentTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                        <i class="ti tabler-user-check ti-xs me-1"></i> البيانات الشخصية
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">
                        <i class="ti tabler-lock ti-xs me-1"></i> المسار الأكاديمي
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="scores-tab" data-bs-toggle="tab" data-bs-target="#scores" type="button" role="tab">
                        <i class="ti tabler-list-numbers ti-xs me-1"></i> سجل الدرجات
                    </button>
                </li>
            </ul>

            <!-- Tab content -->
            <div class="tab-content p-0 bg-transparent border-0 shadow-none">
                <!-- Personal Info Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0">المعلومات الشخصية والاتصال</h5>
                        </div>
                        <div class="card-body pt-4">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-id"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">الرقم القومي</small>
                                            <span class="fw-medium text-heading">{{ $student->national_id }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-gender-bigender"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">الجنس</small>
                                            <span class="fw-medium text-heading">{{ $student->gender == 'male' ? 'ذكر' : 'أنثى' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-calendar"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">تاريخ الميلاد</small>
                                            <span class="fw-medium text-heading">{{ $student->birth_date ? \Carbon\Carbon::parse($student->birth_date)->format('Y-m-d') : 'غير مسجل' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-pray"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">الديانة</small>
                                            <span class="fw-medium text-heading">{{ $student->religion ?? 'غير مسجل' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-map-pin"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">محل الإقامة (العنوان)</small>
                                            <span class="fw-medium text-heading">{{ $student->address ?? 'غير مسجل' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded"><i class="ti tabler-phone-call"></i></div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">الهاتف الأرضي</small>
                                            <span class="fw-medium text-heading">{{ $student->landline_phone ?? 'غير مسجل' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Tab -->
                <div class="tab-pane fade" id="academic" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header border-bottom">
                            <h5 class="card-title mb-0">الشهادة والمسار الدراسي</h5>
                        </div>
                        <div class="card-body pt-4">
                            <h6 class="text-muted text-uppercase small fw-bold mb-3">تفاصيل الشهادة الثانوية</h6>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded border-start border-primary border-4">
                                        <small class="text-muted d-block">نوع الشهادة</small>
                                        <span class="fw-bold text-heading">{{ $student->certificateType?->name ?? 'غير مسجل' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border-start border-info border-4">
                                        <small class="text-muted d-block">رقم الجلوس</small>
                                        <span class="fw-bold text-heading">{{ $student->seat_number ?? '-' }}</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="p-3 bg-light rounded border-start border-success border-4">
                                        <small class="text-muted d-block">سنة التخرج</small>
                                        <span class="fw-bold text-heading">{{ $student->graduation_date ? \Carbon\Carbon::parse($student->graduation_date)->format('Y') : '-' }}</span>
                                    </div>
                                </div>
                            </div>

                            <h6 class="text-muted text-uppercase small fw-bold mb-3">بيانات ولي الأمر</h6>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label text-muted small d-block">وظيفة ولي الأمر</label>
                                    <span class="fw-medium text-heading">{{ $student->guardian_job ?? 'غير مسجل' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small d-block">رقم هاتف ولي الأمر (1)</label>
                                    <span class="fw-medium text-primary">{{ $student->guardian_phone_1 ?? 'غير مسجل' }}</span>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small d-block">رقم هاتف ولي الأمر (2)</label>
                                    <span class="fw-medium text-primary">{{ $student->guardian_phone_2 ?? '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scores Tab -->
                <div class="tab-pane fade" id="scores" role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                            <h5 class="card-title mb-0">سجل درجات المواد والمتطلبات</h5>
                            <span class="badge bg-label-primary">{{ $student->scores->count() }} مواد</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="py-3">المادة / المتطلب</th>
                                        <th class="text-center py-3">الدرجة المستحقة</th>
                                        <th class="text-center py-3">الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($student->scores as $score)
                                        <tr>
                                            <td class="fw-medium text-heading">{{ $score->requirement?->subject_name ?? 'غير معروف' }}</td>
                                            <td class="text-center">
                                                <span class="fw-bold fs-5 text-primary">{{ $score->score }}</span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-label-success">ناجح</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="ti tabler-notes-off d-block mb-2 fs-1"></i>
                                                    لا توجد درجات مسجلة لهذا الطالب حتى الآن
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
