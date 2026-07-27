<div>
    <h4 class="mb-4">مرحباً بك، <strong>{{ $student->name }}</strong> 👋</h4>

    @if ($hasUnpaidFees)
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <span class="alert-icon text-danger me-2">
                        <i class="ti ti-alert-circle ti-xl"></i>
                    </span>
                    <div class="d-flex flex-column">
                        <h5 class="mb-1 alert-heading">تنبيه هام - رسوم غير مدفوعة</h5>
                        <span>يوجد لديك {{ $unpaidFeeTickets->count() }} فاتورة/فاتورات رسوم غير مدفوعة. برجاء السداد في أقرب وقت لتفادي تعليق الخدمات.</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($activeWarnings->isNotEmpty())
        <div class="row mb-4">
            <div class="col-12">
                <div class="card {{ $dangerCount > 0 ? 'border border-danger' : 'border border-warning' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="{{ $warningColorClass }} mb-1 fw-bold">
                                    <i class="ti ti-alert-triangle me-1"></i>
                                    عدد الانذارات الاكاديمية ({{ $warningCount }})
                                </h5>
                                @if ($dangerCount > 0)
                                    <span class="badge bg-label-danger">{{ $dangerCount }} إنذار خطر</span>
                                @endif
                                @if ($warningOnlyCount > 0)
                                    <span class="badge bg-label-warning">{{ $warningOnlyCount }} إنذار تحذير</span>
                                @endif
                            </div>
                        </div>

                        <div class="alert alert-info mb-0 p-3">
                            <h6 class="fw-bold mb-2 text-info">
                                <i class="ti ti-info-circle me-1"></i>
                                تنبيه حول الانذارات الاكاديمية
                            </h6>
                            <p class="mb-1">على الطالب الذي وجه له إنذار اكاديمي أن يرفع <b>معدله التراكمي (CGPA)</b> إلى <b>({{ $warningThreshold }})</b> فما فوق لإلغاء مفعول الإنذار في مدة أقصاها فصلين دراسيين.</p>
                            <p class="mb-0">يفصل الطالب من التخصص إذا أخفق في رفع <b>معدله التراكمي (CGPA)</b> إلى <b>({{ $warningThreshold }})</b> بعد مرور مدة فصلين دراسيين متتاليين.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            @foreach ($activeWarnings as $warning)
                <div class="col-md-6 col-12">
                    <div class="alert {{ $warning->type->value === 'danger' ? 'alert-danger' : 'alert-warning' }} my-1">
                        <span class="fw-bold d-block mb-1">
                            <i class="ti {{ $warning->type->value === 'danger' ? 'ti-alert-octagon' : 'ti-alert-circle' }} me-1"></i>
                            {{ $warning->type->label() }} أكاديمي
                        </span>
                        <h6 class="mb-0">{{ $warning->reason }}</h6>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>المعدل التراكمي</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $stats['cgpa'] }}</h3>
                            </div>
                            <p class="mb-0 text-muted">من 4.0</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded {{ $cgpaColorClass }} bg-opacity-10">
                                <i class="ti ti-chart-line ti-sm text-white"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>الساعات المجتازة</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $stats['earned_hours'] }}</h3>
                            </div>
                            <p class="mb-0 text-muted">ساعة دراسية</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-clock ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>ساعات التخرج المطلوبة</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $requiredHours }}</h3>
                            </div>
                            <p class="mb-0 text-muted">ساعة دراسية</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-graduation-cap ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>الفرقة الدراسية</span>
                            <div class="d-flex align-items-center my-2">
                                <h3 class="mb-0 me-2">{{ $student->level?->name ?? '—' }}</h3>
                            </div>
                            <p class="mb-0 text-muted">{{ $student->section?->name ?? 'غير محدد' }}</p>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ti ti-school ti-sm"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title m-0 me-2">المعدل التراكمي (CGPA)</h5>
                    <span class="badge bg-label-{{ $stats['cgpa'] >= 3 ? 'success' : ($stats['cgpa'] >= 2 ? 'warning' : 'danger') }} p-2 px-3 rounded-pill">{{ $stats['cgpa'] }} / 4.0</span>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 30px;">
                        <div
                            class="progress-bar {{ $stats['cgpa'] >= 3 ? 'bg-success' : ($stats['cgpa'] >= 2 ? 'bg-warning' : 'bg-danger') }}"
                            role="progressbar"
                            style="width: {{ min(($stats['cgpa'] / 4.0) * 100, 100) }}%"
                            aria-valuenow="{{ $stats['cgpa'] }}"
                            aria-valuemin="0"
                            aria-valuemax="4">
                            <span class="fw-bold h6 mb-0 text-white">{{ $stats['cgpa'] }}</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">0.0</small>
                        <small class="text-muted">عتب التحذير: {{ $warningThreshold }}</small>
                        <small class="text-muted">4.0</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title m-0 me-2">الساعات المكتسبة</h5>
                    <span class="badge bg-label-primary p-2 px-3 rounded-pill">{{ $stats['earned_hours'] }} / {{ $requiredHours }} ساعة</span>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 30px;">
                        <div
                            class="progress-bar bg-primary"
                            role="progressbar"
                            style="width: {{ min(($stats['earned_hours'] / $requiredHours) * 100, 100) }}%"
                            aria-valuenow="{{ $stats['earned_hours'] }}"
                            aria-valuemin="0"
                            aria-valuemax="{{ $requiredHours }}">
                            <span class="fw-bold h6 mb-0 text-white">{{ $stats['earned_hours'] }}</span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <small class="text-muted">0 ساعة</small>
                        <small class="text-muted">متبقي: {{ max($requiredHours - $stats['earned_hours'], 0) }} ساعة</small>
                        <small class="text-muted">{{ $requiredHours }} ساعة</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        @if ($student->academicAdvisor)
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-primary">
                            <i class="ti ti-user ti-xl"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0">المرشد الأكاديمي</h5>
                            <small class="text-muted">المرشد المسؤول عن متابعتك الأكاديمية</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <h4 class="mb-0 fw-bold">{{ $student->academicAdvisor->name }}</h4>
                        @if (! empty($student->academicAdvisor->email))
                            <p class="text-muted mt-2 mb-0">
                                <i class="ti ti-mail me-1"></i>{{ $student->academicAdvisor->email }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($student->gender === 'ذكر')
            <div class="col-md-6 col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-{{ $student->hasPassedMilitaryEducation() ? 'success' : 'warning' }}">
                            <i class="ti ti-shield ti-xl"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-0">نتيجة التدريب العسكري</h5>
                            <small class="text-muted">حالة إتمام متطلبات التدريب العسكري</small>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($student->hasPassedMilitaryEducation())
                            <div class="d-flex align-items-center">
                                <span class="badge bg-label-success p-2 px-3 fs-6 rounded-pill">
                                    <i class="ti ti-check me-1"></i>تم الإنجاز بنجاح
                                </span>
                            </div>
                        @else
                            <div class="d-flex align-items-center">
                                <span class="badge bg-label-warning p-2 px-3 fs-6 rounded-pill">
                                    <i class="ti ti-clock-hour-4 me-1"></i>قيد الإنجاز / غير مكتمل
                                </span>
                            </div>
                            @if ($lastMil = $student->lastMilitaryEducationEnrollment())
                                <p class="text-muted mt-2 mb-0">
                                    آخر تسجيل: {{ $lastMil->militaryEducationCourse?->name ?? '—' }}
                                    @if (! empty($lastMil->status))
                                        <span class="badge bg-label-secondary ms-1">{{ $lastMil->status->label() }}</span>
                                    @endif
                                </p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="row g-4 mb-4">
        @if (! empty($student->seat_number))
            <div class="col-md-4 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-md me-3 bg-label-info">
                                <i class="ti ti-id ti-xl"></i>
                            </div>
                            <div>
                                <p class="text-muted mb-1">رقم الجلوس</p>
                                <h4 class="fw-bold mb-0">{{ $student->seat_number }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($student->section)
            <div class="col-md-4 col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-md me-3 bg-label-secondary">
                                <i class="ti ti-users-group ti-xl"></i>
                            </div>
                            <div>
                                <p class="text-muted mb-1">رقم السكشن / الشعبة</p>
                                <h4 class="fw-bold mb-0">{{ $student->section->name }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-md-4 col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md me-3 bg-label-dark">
                            <i class="ti ti-barcode ti-xl"></i>
                        </div>
                        <div>
                            <p class="text-muted mb-1">الكود الجامعي</p>
                            <h4 class="fw-bold mb-0">{{ $student->username }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center">
                    <div class="avatar avatar-md me-3 bg-label-primary">
                        <i class="ti ti-clipboard-check ti-xl"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-0">حالة تسجيل الترم الحالي</h5>
                        <small class="text-muted">
                            @if ($currentYear && $currentSemester)
                                العام الجامعي: {{ $currentYear->name }} - {{ $currentSemester->label() }}
                            @else
                                لم يتم تحديد العام أو الترم الحالي
                            @endif
                        </small>
                    </div>
                </div>
                <div class="card-body">
                    @if ($currentRegistration)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="alert {{ $currentRegistration->status === \App\Enums\RegistrationStatus::APPROVED ? 'alert-success' : ($currentRegistration->status === \App\Enums\RegistrationStatus::REJECTED ? 'alert-danger' : 'alert-warning') }} mb-0">
                                    <h6 class="mb-1 fw-bold d-flex align-items-center">
                                        @if ($currentRegistration->status === \App\Enums\RegistrationStatus::APPROVED)
                                            <i class="ti ti-check me-2"></i>
                                        @elseif ($currentRegistration->status === \App\Enums\RegistrationStatus::REJECTED)
                                            <i class="ti ti-x me-2"></i>
                                        @else
                                            <i class="ti ti-clock-hour-4 me-2"></i>
                                        @endif
                                        حالة التسجيل: {{ $currentRegistration->status->label() }}
                                    </h6>
                                    @if ($currentRegistration->status === \App\Enums\RegistrationStatus::REJECTED && ! empty($currentRegistration->rejection_reason))
                                        <p class="mb-0 text-sm">سبب الرفض: {{ $currentRegistration->rejection_reason }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert {{ $currentRegistration->approvedByAdvisor ? 'alert-success' : 'alert-warning' }} mb-0">
                                    <h6 class="mb-1 fw-bold d-flex align-items-center">
                                        @if ($currentRegistration->approvedByAdvisor)
                                            <i class="ti ti-user-check me-2"></i>
                                        @else
                                            <i class="ti ti-user-clock me-2"></i>
                                        @endif
                                        موافقة الإرشاد الأكاديمي:
                                        {{ $currentRegistration->approvedByAdvisor ? 'تمت الموافقة' : 'قيد الانتظار' }}
                                    </h6>
                                    @if ($currentRegistration->approvedByAdvisor)
                                        <p class="mb-0 text-sm">بواسطة: {{ $currentRegistration->approvedByAdvisor->name }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert {{ $currentRegistration->approvedByUser ? 'alert-success' : 'alert-warning' }} mb-0">
                                    <h6 class="mb-1 fw-bold d-flex align-items-center">
                                        @if ($currentRegistration->approvedByUser)
                                            <i class="ti ti-building-check me-2"></i>
                                        @else
                                            <i class="ti ti-building-clock me-2"></i>
                                        @endif
                                        موافقة الشؤون المالية:
                                        {{ $currentRegistration->approvedByUser ? 'تمت الموافقة' : 'قيد الانتظار' }}
                                    </h6>
                                    @if ($currentRegistration->approvedByUser)
                                        <p class="mb-0 text-sm">بواسطة: {{ $currentRegistration->approvedByUser->name }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert alert-info mb-0">
                                    <h6 class="mb-1 fw-bold d-flex align-items-center">
                                        <i class="ti ti-book me-2"></i>
                                        عدد المواد المسجلة: {{ $currentRegistration->courses->count() }} مادة
                                    </h6>
                                    <p class="mb-0 text-sm">
                                        إجمالي الساعات: {{ $currentRegistration->courses->sum(fn($c) => $c->course?->hours ?? 0) }} ساعة
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning mb-0 text-center py-4">
                            <i class="ti ti-alert-triangle ti-2xl mb-2 d-block text-warning"></i>
                            <h6 class="mb-0 fw-bold">أنت لم تقم بالتسجيل في هذا الترم حتى الآن</h6>
                            <p class="mb-0 mt-2 text-muted">برجاء الانتقال إلى صفحة تسجيل المواد لبدء التسجيل.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
