<div>
    {{-- Header --}}
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">البحث عن طالب</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">البحث عن طالب</li>
                </ol>
            </nav>
        </div>
    </div>

    {{-- Search Card --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form wire:submit.prevent="search" class="row gx-3 gy-2 align-items-end">
                <div class="col-sm-8 col-md-6">
                    <label class="form-label fw-medium" for="searchCode">كود الطالب</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input
                            type="text"
                            class="form-control @error('searchCode') is-invalid @enderror"
                            id="searchCode"
                            wire:model="searchCode"
                            placeholder="أدخل كود الطالب (مثال: E260001)"
                            autofocus
                        >
                        @error('searchCode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading wire:target="search" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        <i wire:loading.remove wire:target="search" class="ti tabler-search me-1"></i>
                        بحث
                    </button>
                    @if($student || $searched)
                        <button type="button" class="btn btn-label-secondary ms-1" wire:click="clear">
                            <i class="ti tabler-x me-1"></i> مسح
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Not Found --}}
    @if($searched && !$student)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ti tabler-user-off d-block mb-3 text-muted" style="font-size: 3rem;"></i>
                <h5 class="text-muted">لم يتم العثور على طالب</h5>
                <p class="text-muted small mb-0">لا يوجد طالب مسجل بالكود: <strong>{{ $searchCode }}</strong></p>
            </div>
        </div>
    @endif

    {{-- Student Data --}}
    @if($student)
        <div class="row g-4">
            {{-- Profile Card --}}
            <div class="col-xl-4 col-lg-4 col-md-5">
                <div class="card border-0 shadow-sm overflow-hidden">
                    <div class="card-body text-center pb-3 pt-4">
                        {{-- Avatar --}}
                        <div class="position-relative d-inline-block mb-3">
                            @if($student->image)
                                <img
                                    src="{{ asset('storage/' . $student->image) }}"
                                    class="img-fluid rounded-3 shadow-sm"
                                    width="100" height="100"
                                    alt="صورة الطالب"
                                >
                            @else
                                <div class="avatar avatar-xl">
                                    <span class="avatar-initial rounded-3 bg-label-primary fs-2 shadow-sm">
                                        {{ mb_substr($student->name, 0, 1) }}
                                    </span>
                                </div>
                            @endif

                            @php
                                $dotColor = match($student->status?->value) {
                                    'registered' => 'bg-success',
                                    'suspended'  => 'bg-warning',
                                    'dismissed'  => 'bg-danger',
                                    default       => 'bg-secondary',
                                };
                            @endphp
                            <span class="position-absolute bottom-0 end-0 p-1 {{ $dotColor }} border border-2 border-white rounded-circle">
                                <span class="visually-hidden">حالة الطالب</span>
                            </span>
                        </div>

                        <h5 class="mb-1 fw-bold">{{ $student->name }}</h5>
                        <span class="badge bg-label-secondary mb-3">{{ $student->username }}</span>

                        {{-- Quick stats --}}
                        <div class="d-flex justify-content-center gap-4 border-top border-bottom py-3 mb-3">
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold">{{ $student->warnings->count() }}</h6>
                                <small class="text-muted">الإنذارات</small>
                            </div>
                            <div class="vr"></div>
                            <div class="text-center">
                                <h6 class="mb-0 fw-bold">{{ $student->warnings->where('is_active', true)->count() }}</h6>
                                <small class="text-muted">نشطة</small>
                            </div>
                        </div>

                        {{-- Info list --}}
                        <ul class="list-unstyled text-start mb-0">
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="ti tabler-id text-primary"></i>
                                <span class="fw-medium small">الكود:</span>
                                <span class="small text-muted ms-auto">{{ $student->username }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="ti tabler-school text-info"></i>
                                <span class="fw-medium small">الفرقة:</span>
                                <span class="small text-muted ms-auto">{{ $student->level?->name ?? 'غير محدد' }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="ti tabler-subtask text-warning"></i>
                                <span class="fw-medium small">التخصص:</span>
                                <span class="small text-muted ms-auto">{{ $student->section?->name ?? 'غير محدد' }}</span>
                            </li>
                            <li class="d-flex align-items-center gap-2 mb-2">
                                <i class="ti tabler-user-check text-success"></i>
                                <span class="fw-medium small">المرشد:</span>
                                <span class="small text-muted ms-auto">{{ $student->academicAdvisor?->name ?? 'غير محدد' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- Notes card --}}
                @if($student->status_notes)
                    <div class="card border-0 shadow-sm bg-label-warning mt-3">
                        <div class="card-body">
                            <h6 class="card-title fw-bold mb-2">
                                <i class="ti tabler-notes me-1"></i> ملاحظات
                            </h6>
                            <p class="card-text small mb-0">{{ $student->status_notes }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Details --}}
            <div class="col-xl-8 col-lg-8 col-md-7">

                {{-- Main info card --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header border-bottom">
                        <h5 class="card-title mb-0">
                            <i class="ti tabler-file-description me-2 text-primary"></i>
                            بيانات الطالب التفصيلية
                        </h5>
                    </div>
                    <div class="card-body pt-4">
                        <div class="row g-4">
                            {{-- Name --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-primary rounded">
                                            <i class="ti tabler-user"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">اسم الطالب</small>
                                        <span class="fw-medium text-heading">{{ $student->name }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Code --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-secondary rounded">
                                            <i class="ti tabler-id-badge"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">كود الطالب</small>
                                        <span class="fw-medium text-heading">{{ $student->username }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Level --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-info rounded">
                                            <i class="ti tabler-school"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">الفرقة الدراسية</small>
                                        <span class="fw-medium text-heading">{{ $student->level?->name ?? 'غير محدد' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Section (specialization) --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-warning rounded">
                                            <i class="ti tabler-subtask"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">التخصص</small>
                                        <span class="fw-medium text-heading">{{ $student->section?->name ?? 'غير محدد' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Password --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-danger rounded">
                                            <i class="ti tabler-lock"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">كلمة المرور</small>
                                        <span class="fw-medium text-heading font-monospace">{{ $student->plain_password ?? '••••••••' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Study Status --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-success rounded">
                                            <i class="ti tabler-book-2"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">الحالة الدراسية</small>
                                        <span class="fw-medium text-heading">{{ $student->study_status?->label() ?? 'غير محدد' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Academic Advisor --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-primary rounded">
                                            <i class="ti tabler-user-check"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">المرشد الأكاديمي</small>
                                        <span class="fw-medium text-heading">{{ $student->academicAdvisor?->name ?? 'غير محدد' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Student Status --}}
                            <div class="col-md-6">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <div class="avatar-initial bg-label-secondary rounded">
                                            <i class="ti tabler-toggle-left"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <small class="text-muted d-block">حالة الطالب</small>
                                        @php
                                            $statusClass = match($student->status?->value) {
                                                'registered' => 'bg-label-success',
                                                'excused'    => 'bg-label-info',
                                                'suspended'  => 'bg-label-warning',
                                                'dismissed'  => 'bg-label-danger',
                                                'withdrawn'  => 'bg-label-secondary',
                                                'graduated'  => 'bg-label-primary',
                                                default       => 'bg-label-secondary',
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }}">{{ $student->status?->label() ?? 'غير محدد' }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Notes --}}
                            @if($student->status_notes)
                                <div class="col-12">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="avatar flex-shrink-0">
                                            <div class="avatar-initial bg-label-secondary rounded">
                                                <i class="ti tabler-notes"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <small class="text-muted d-block">الملاحظات</small>
                                            <span class="fw-medium text-heading">{{ $student->status_notes }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Warnings card --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-header border-bottom d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ti tabler-alert-triangle me-2 text-warning"></i>
                            سجل الإنذارات
                        </h5>
                        <span class="badge bg-label-{{ $student->warnings->where('is_active', true)->count() > 0 ? 'danger' : 'success' }}">
                            {{ $student->warnings->where('is_active', true)->count() }} نشط
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3">النوع</th>
                                    <th class="py-3">السبب</th>
                                    <th class="py-3">تاريخ الإضافة</th>
                                    <th class="py-3 text-center">الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($student->warnings as $warning)
                                    <tr>
                                        <td>
                                            @if($warning->type->value === \App\Enums\Student\StudentWarningType::DANGER->value)
                                                <span class="badge bg-label-danger">
                                                    <i class="ti tabler-alert-octagon me-1"></i> خطر
                                                </span>
                                            @else
                                                <span class="badge bg-label-warning">
                                                    <i class="ti tabler-alert-triangle me-1"></i> تحذير
                                                </span>
                                            @endif
                                        </td>
                                        <td style="white-space: pre-wrap; max-width: 300px;">{{ $warning->reason }}</td>
                                        <td class="text-nowrap">{{ $warning->created_at->format('Y-m-d H:i') }}</td>
                                        <td class="text-center">
                                            @if($warning->is_active)
                                                <span class="badge bg-label-success">نشط</span>
                                            @else
                                                <span class="badge bg-label-secondary">ملغي</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="ti tabler-circle-check d-block mb-2 fs-2 text-success"></i>
                                                لا توجد إنذارات مسجلة لهذا الطالب
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
    @endif
</div>
