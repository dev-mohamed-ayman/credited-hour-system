<div>
    <!-- Header -->
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">إدارة دورات التربية العسكرية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">دورات التربية العسكرية</li>
                </ol>
            </nav>
        </div>
        <button type="button" wire:click="$set('showCreateModal', true)" class="btn btn-primary shadow-sm">
            <i class="ti tabler-plus me-1"></i> إنشاء دورة جديدة
        </button>
    </div>

    <!-- Filters Card -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="بحث باسم الدورة...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="gender" class="form-select">
                        <option value="">كل الأنواع</option>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="status" class="form-select">
                        <option value="">كل الحالات</option>
                        <option value="active">مفتوحة</option>
                        <option value="closed">مغلقة</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" wire:click="reset(['search', 'gender', 'status'])" class="btn btn-label-secondary w-100">
                        <i class="ti tabler-refresh me-1"></i> إعادة تعيين
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Courses Table -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>اسم الدورة</th>
                        <th>النوع</th>
                        <th class="text-center">السعة</th>
                        <th class="text-center">المسجلين</th>
                        <th class="text-center">المصاريف</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($courses as $course)
                        <tr>
                            <td class="fw-medium">
                                <a href="{{ route('military-education-courses.show', $course) }}" class="text-heading text-decoration-none hover-primary">{{ $course->name }}</a>
                            </td>
                            <td>{{ $course->gender == 'male' ? 'ذكر' : 'أنثى' }}</td>
                            <td class="text-center">{{ $course->capacity }}</td>
                            <td class="text-center">
                                <span class="badge {{ $course->enrollments_count >= $course->capacity ? 'bg-label-danger' : 'bg-label-info' }}">{{ $course->enrollments_count }}</span>
                            </td>
                            <td class="text-center">{{ number_format($course->fee_amount, 2) }}</td>
                            <td class="text-center">
                                @php
                                    $statusClass = match($course->status?->value) {
                                        'active' => 'bg-label-success',
                                        'closed' => 'bg-label-secondary',
                                        default => 'bg-label-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $course->status?->label() ?? 'غير محدد' }}</span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-block">
                                    <a href="{{ route('military-education-courses.show', $course) }}" class="btn btn-sm btn-icon btn-label-primary">
                                        <i class="ti tabler-eye"></i>
                                    </a>
                                    @if($course->status?->value === 'active')
                                        <button type="button" class="btn btn-sm btn-icon btn-label-warning ms-1" onclick="confirmAction('إغلاق الدورة', 'هل أنت متأكد من إغلاق هذه الدورة؟', () => @this.call('closeCourse', {{ $course->id }}))">
                                            <i class="ti tabler-lock"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="ti tabler-notes-off d-block mb-2 fs-1"></i>
                                    لا توجد دورات مطابقة للبحث
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer border-top pt-4">
            {{ $courses->links() }}
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal fade @if($showCreateModal) show d-block @endif" id="createCourseModal" tabindex="-1" aria-hidden="true" style="background: rgba(0,0,0,0.5)">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">إنشاء دورة جديدة</h5>
                    <button type="button" class="btn-close" wire:click="$set('showCreateModal', false)" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">اسم الدورة</label>
                            <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="مثال: دورة التربية العسكرية - يوليو 2025">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">النوع</label>
                            <select wire:model="selectedGender" class="form-select @error('selectedGender') is-invalid @enderror">
                                <option value="">اختر النوع</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                            @error('selectedGender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">السعة (عدد الطلاب)</label>
                            <input type="number" wire:model="capacity" class="form-control @error('capacity') is-invalid @enderror" min="1">
                            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">المصاريف</label>
                            <input type="number" wire:model="feeAmount" class="form-control @error('feeAmount') is-invalid @enderror" min="0" step="0.01">
                            @error('feeAmount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" wire:click="$set('showCreateModal', false)">إلغاء</button>
                    <button type="button" class="btn btn-primary" wire:click="createCourse" wire:loading.attr="disabled">
                        <span wire:loading class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        إنشاء الدورة
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
