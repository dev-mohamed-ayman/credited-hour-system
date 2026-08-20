<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">سجلات التسجيل</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">سجلات التسجيل</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">تصفية السجلات</h5>
        </div>
        <div class="card-body pt-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="searchYear" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="searchYear" id="searchYear" class="form-select">
                        <option value="">الكل</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="searchSemester" class="form-label fw-bold">الترم</label>
                    <select wire:model.live="searchSemester" id="searchSemester" class="form-select">
                        <option value="">الكل</option>
                        @foreach(\App\Enums\Semester::cases() as $semester)
                            <option value="{{ $semester->value }}">{{ $semester->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-label-secondary w-100" wire:click="clearFilters">مسح الفلاتر</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>الفرقة</th>
                        <th>السنة / الترم</th>
                        <th class="text-center">عدد المواد</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center" wire:click="sortBy('created_at')" style="cursor: pointer;">
                            تاريخ التسجيل
                            @if($sortField === 'created_at')
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            @endif
                        </th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($registrations as $registration)
                        <tr>
                            <td>
                                <span class="badge bg-label-secondary">{{ $registration->student->level?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <div>{{ $registration->year->year }}</div>
                                <small class="text-muted">{{ $registration->semester->label() }}</small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-label-info">{{ $registration->courses->count() }} مواد</span>
                            </td>
                            <td class="text-center">
                                @if($registration->status === \App\Enums\RegistrationStatus::PENDING)
                                    <span class="badge bg-label-warning">{{ $registration->status->label() }}</span>
                                @elseif($registration->status === \App\Enums\RegistrationStatus::APPROVED)
                                    <span class="badge bg-label-success">{{ $registration->status->label() }}</span>
                                @else
                                    <span class="badge bg-label-danger">{{ $registration->status->label() }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                {{ $registration->created_at->format('Y-m-d') }}
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <button type="button" class="btn btn-icon btn-sm btn-label-primary" data-bs-toggle="modal" data-bs-target="#showRegistrationModal{{ $registration->id }}" title="عرض السجل">
                                        <i class="ti tabler-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="ti tabler-clipboard-data d-block mb-2" style="font-size: 3rem;"></i>
                                لا توجد سجلات تسجيل مطابقة للبحث
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($registrations->hasPages())
            <div class="card-footer d-flex justify-content-center pb-0">
                {{ $registrations->links() }}
            </div>
        @endif
    </div>

    @foreach($registrations as $registration)
        <div class="modal fade" id="showRegistrationModal{{ $registration->id }}" tabindex="-1" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="showRegistrationModalLabel{{ $registration->id }}">تفاصيل التسجيل - {{ $registration->year->year }} - {{ $registration->semester->label() }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">الحالة</label>
                                    <div>
                                        @if($registration->status === \App\Enums\RegistrationStatus::PENDING)
                                            <span class="badge bg-label-warning">{{ $registration->status->label() }}</span>
                                        @elseif($registration->status === \App\Enums\RegistrationStatus::APPROVED)
                                            <span class="badge bg-label-success">{{ $registration->status->label() }}</span>
                                        @else
                                            <span class="badge bg-label-danger">{{ $registration->status->label() }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">تاريخ التسجيل</label>
                                    <div>{{ $registration->created_at->format('Y-m-d H:i') }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted small mb-1">الرسوم المخصومة من المحفظة</label>
                            <div>
                                <span class="badge bg-label-{{ (float) $registration->charged_amount > 0 ? 'success' : 'secondary' }} fs-6">
                                    {{ number_format((float) $registration->charged_amount, 2) }} ج.م
                                </span>
                            </div>
                        </div>
                        @if($registration->rejection_reason)
                            <div class="alert alert-danger mb-4">
                                <h6 class="alert-heading fw-bold mb-1">سبب الرفض:</h6>
                                <p class="mb-0">{{ $registration->rejection_reason }}</p>
                            </div>
                        @endif

                        @if($registration->approvedByUser || $registration->approvedByAdvisor)
                            <div class="alert alert-info mb-4">
                                <h6 class="alert-heading fw-bold mb-1">تمت الموافقة بواسطة:</h6>
                                <p class="mb-0">
                                    {{ $registration->approvedByUser?->name ?? $registration->approvedByAdvisor?->name ?? '—' }}
                                </p>
                            </div>
                        @endif

                        <h6 class="fw-bold mb-3">المواد المسجلة:</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>اسم المادة</th>
                                        <th class="text-center">الساعات</th>
                                        <th class="text-center">النوع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($registration->courses as $registrationCourse)
                                        <tr>
                                            <td>{{ $registrationCourse->course->name }}</td>
                                            <td class="text-center">{{ $registrationCourse->course->hours }}</td>
                                            <td class="text-center">
                                                @if($registrationCourse->course->is_selected)
                                                    <span class="badge bg-label-info">اختياري</span>
                                                @else
                                                    <span class="badge bg-label-success">إجباري</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
