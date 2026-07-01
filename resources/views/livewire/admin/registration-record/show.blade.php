<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">تفاصيل السجل التاريخي للتسجيل</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('registration-records.index') }}">سجلات التسجيل</a></li>
                    <li class="breadcrumb-item active">تفاصيل السجل</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Readonly Info -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-2 mb-3 mb-md-0 d-flex justify-content-center">
                    <div class="avatar avatar-xl">
                        <span class="avatar-initial rounded-circle bg-label-primary fs-3"><i class="ti tabler-user"></i></span>
                    </div>
                </div>
                <div class="col-md-10">
                    <div class="row g-3">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">اسم الطالب</label>
                            <div class="fw-bold fs-6">{{ $registration->student->name }}</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">كود الطالب</label>
                            <div class="fw-bold fs-6">{{ $registration->student->username }}</div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">الفرقة والتخصص</label>
                            <div class="fw-bold fs-6">
                                {{ $registration->student->level?->name ?? '—' }}
                                <span class="mx-1">|</span>
                                {{ $registration->student->section?->department?->name ?? '—' }}
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">بيانات التسجيل (تاريخية)</label>
                            <div class="fw-bold fs-6 text-primary">
                                {{ $registration->year->year }}
                                <span class="mx-1">-</span>
                                {{ $registration->semester->label() }}
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">تم التسجيل بواسطة</label>
                            <div class="fw-bold fs-6">
                                @if($registration->createdByUser)
                                    <span class="badge bg-label-info">الأدمن: {{ $registration->createdByUser->name }}</span>
                                @elseif($registration->createdByAdvisor)
                                    <span class="badge bg-label-primary">المرشد: {{ $registration->createdByAdvisor->name }}</span>
                                @else
                                    <span class="badge bg-label-secondary">غير محدد</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="text-muted small mb-1">حالة التسجيل</label>
                            <div class="fw-bold fs-6">
                                @if($registration->status === \App\Enums\RegistrationStatus::PENDING)
                                    <span class="badge bg-label-warning">{{ $registration->status->label() }}</span>
                                @elseif($registration->status === \App\Enums\RegistrationStatus::APPROVED)
                                    <span class="badge bg-label-success">{{ $registration->status->label() }}</span>
                                @else
                                    <span class="badge bg-label-danger">{{ $registration->status->label() }}</span>
                                @endif
                            </div>
                        </div>
                        @if($registration->approvedByUser)
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="text-muted small mb-1">تمت الموافقة بواسطة</label>
                                <div class="fw-bold fs-6">
                                    <span class="badge bg-label-success">الأدمن: {{ $registration->approvedByUser->name }}</span>
                                </div>
                            </div>
                        @elseif($registration->approvedByAdvisor)
                            <div class="col-12 col-sm-6 col-lg-3">
                                <label class="text-muted small mb-1">تمت الموافقة بواسطة</label>
                                <div class="fw-bold fs-6">
                                    <span class="badge bg-label-success">المرشد: {{ $registration->approvedByAdvisor->name }}</span>
                                </div>
                            </div>
                        @endif
                        @if($registration->rejection_reason)
                            <div class="col-12">
                                <label class="text-muted small mb-1">سبب الرفض</label>
                                <div class="alert alert-danger mb-0">{{ $registration->rejection_reason }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if($registration->status === \App\Enums\RegistrationStatus::PENDING && auth()->user()->can('course_registrations.create'))
            <div class="card-footer pt-0">
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectRegistrationModal">
                        <i class="ti tabler-x me-1"></i>رفض التسجيل
                    </button>
                    <button type="button" class="btn btn-success" wire:click="approveRegistration">
                        <i class="ti tabler-check me-1"></i>موافقة على التسجيل
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Courses Table -->
    <div class="card">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">المواد المسجلة في هذا السجل</h5>
            @can('course_registrations.create')
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="ti tabler-plus me-1"></i>
                    إضافة مادة للسجل
                </button>
            @endcan
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>كود المادة</th>
                        <th>اسم المادة</th>
                        <th class="text-center">الساعات</th>
                        <th class="text-center">النوع</th>
                        <th class="text-center">التقدير (الحالي)</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($registration->courses as $registrationCourse)
                        <tr>
                            <td><strong>{{ $registrationCourse->course->code }}</strong></td>
                            <td>{{ $registrationCourse->course->name }}</td>
                            <td class="text-center">
                                <span class="badge bg-label-secondary">{{ $registrationCourse->course->hours }} ساعة</span>
                            </td>
                            <td class="text-center">
                                @if($registrationCourse->course->is_selected)
                                    <span class="badge bg-label-info">اختياري</span>
                                @else
                                    <span class="badge bg-label-success">إجباري</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($registrationCourse->grade)
                                    @php
                                        $isPending = $registrationCourse->grade->is_pending_default;
                                        $isSuccess = !$isPending && $registrationCourse->grade->degree >= 50; // Simple fallback color logic, actual logic relies on failing grades
                                    @endphp
                                    <span class="badge {{ $isPending ? 'bg-label-warning' : ($isSuccess ? 'bg-label-success' : 'bg-label-danger') }}">
                                        {{ $registrationCourse->grade->name }}
                                    </span>
                                @else
                                    <span class="badge bg-label-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @can('course_registrations.delete')
                                    <button type="button" class="btn btn-icon btn-sm btn-label-danger"
                                            wire:click="$dispatch('confirm-deletion', { id: {{ $registrationCourse->id }}, title: 'هل أنت متأكد من إزالة المادة؟', text: 'إزالة هذه المادة من السجل سيحذف ارتباط الطالب بها في هذا الترم.', method: 'deleteCourse' })" title="إزالة المادة">
                                        <i class="ti tabler-trash"></i>
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="ti tabler-book-off d-block mb-2" style="font-size: 3rem;"></i>
                                لا توجد مواد مسجلة في هذا السجل
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div wire:ignore.self class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCourseModalTitle">إضافة مادة تاريخية للسجل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="addCourse">
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center mb-4" role="alert">
                            <i class="ti tabler-info-circle me-2"></i>
                            <div>
                                قائمة المواد المتاحة هنا محسوبة بناءً على <strong>أهلية الطالب في هذا الترم التاريخي فقط</strong> (استبعاداً لأي مواد اجتازها لاحقاً).
                            </div>
                        </div>

                        <div class="mb-3" wire:ignore>
                            <label for="selectedCourseId" class="form-label fw-bold">اختر المادة</label>
                            <select id="selectedCourseId" class="select2 form-select" data-placeholder="-- اختر مادة --">
                                <option value=""></option>
                                @foreach($availableCourses as $course)
                                    <option value="{{ $course->id }}">{{ $course->code }} - {{ $course->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('selectedCourseId')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="addCourse">إضافة المادة</span>
                            <span wire:loading wire:target="addCourse">جاري الإضافة...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Registration Modal -->
    <div wire:ignore.self class="modal fade" id="rejectRegistrationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectRegistrationModalTitle">رفض التسجيل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="rejectRegistration">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="rejectionReason" class="form-label fw-bold">سبب الرفض</label>
                            <textarea id="rejectionReason" wire:model="rejectionReason" class="form-control" rows="3" placeholder="يرجى إدخال سبب رفض التسجيل..."></textarea>
                            @error('rejectionReason')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="rejectRegistration">رفض التسجيل</span>
                            <span wire:loading wire:target="rejectRegistration">جاري الرفض...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', () => {
        const select2El = $('#selectedCourseId');
        
        select2El.select2({
            dropdownParent: $('#addCourseModal'),
            theme: 'bootstrap-5',
            width: '100%',
        });

        select2El.on('change', function (e) {
            @this.set('selectedCourseId', $(this).val());
        });

        Livewire.on('close-modal', (data) => {
            if (data.id === 'addCourseModal') {
                $('#addCourseModal').modal('hide');
                select2El.val(null).trigger('change');
            }
        });
    });
</script>
@endpush
