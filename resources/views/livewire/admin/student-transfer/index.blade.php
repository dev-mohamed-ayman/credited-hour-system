<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">طلبات تحويل التخصص</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">طلبات تحويل التخصص</li>
                </ol>
            </nav>
        </div>
        @can('student_transfers.create')
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTransferModal">
                <i class="ti tabler-plus me-1"></i> طلب تحويل جديد
            </button>
        @endcan
    </div>

    <div class="card mb-4">
        <div class="card-header border-bottom">
            <h5 class="card-title mb-0">تصفية الطلبات</h5>
        </div>
        <div class="card-body pt-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="searchStudent" class="form-label fw-bold">اسم أو كود الطالب</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="searchStudent" id="searchStudent" class="form-control" placeholder="بحث...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="searchDepartment" class="form-label fw-bold">التخصص</label>
                    <select wire:model.live="searchDepartment" id="searchDepartment" class="form-select">
                        <option value="">الكل</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="searchStatus" class="form-label fw-bold">الحالة</label>
                    <select wire:model.live="searchStatus" id="searchStatus" class="form-select">
                        <option value="">الكل</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
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
                        <th>الطالب</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th class="text-center">المسترد</th>
                        <th class="text-center">الحالة</th>
                        <th class="text-center">تاريخ الطلب</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($requests as $request)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-3">
                                        <span class="avatar-initial rounded-circle bg-label-primary"><i class="ti tabler-user"></i></span>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-truncate" style="max-width: 200px;" title="{{ $request->student?->name }}">{{ $request->student?->name ?? '—' }}</h6>
                                        <small class="text-muted">{{ $request->student?->username }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $request->fromDepartment?->name ?? '—' }}</div>
                                <small class="text-muted">{{ $request->fromSection?->name }}</small>
                            </td>
                            <td>
                                <div>{{ $request->toDepartment?->name ?? '—' }}</div>
                                <small class="text-muted">{{ $request->toSection?->name }} — {{ $request->toLevel?->name }}</small>
                            </td>
                            <td class="text-center">
                                @if($request->status === \App\Enums\TransferRequestStatus::APPROVED)
                                    <span class="badge bg-label-info">{{ number_format((float) $request->refunded_amount, 2) }} ج.م</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $request->status->badgeClass() }}">{{ $request->status->label() }}</span>
                            </td>
                            <td class="text-center">{{ $request->created_at->format('Y-m-d') }}</td>
                            <td class="text-center">
                                <a href="{{ route('student-transfers.show', $request->id) }}" class="btn btn-icon btn-sm btn-label-primary" title="تفاصيل الطلب">
                                    <i class="ti tabler-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="ti tabler-arrows-exchange d-block mb-2" style="font-size: 3rem;"></i>
                                لا توجد طلبات تحويل مطابقة للبحث
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer d-flex justify-content-center pb-0">
                {{ $requests->links() }}
            </div>
        @endif
    </div>

    @can('student_transfers.create')
        <div class="modal fade" id="createTransferModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">طلب تحويل تخصص جديد</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-9">
                                <label for="studentCode" class="form-label fw-bold">كود الطالب</label>
                                <input type="text" wire:model="studentCode" id="studentCode" class="form-control @error('studentCode') is-invalid @enderror" placeholder="مثال: CS250001">
                                @error('studentCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-label-primary w-100" wire:click="searchStudent" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="searchStudent"><i class="ti tabler-search me-1"></i> بحث</span>
                                    <span wire:loading wire:target="searchStudent">جارٍ البحث...</span>
                                </button>
                            </div>
                        </div>

                        @if($student)
                            <div class="alert alert-primary d-flex align-items-center mb-4" role="alert">
                                <i class="ti tabler-user-check me-2"></i>
                                <div>
                                    <strong>{{ $student->name }}</strong> ({{ $student->username }})
                                    <div class="small">
                                        التخصص الحالي: {{ $student->section?->department?->name ?? '—' }}
                                        — الشعبة: {{ $student->section?->name ?? '—' }}
                                        — {{ $student->level?->name ?? '—' }}
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="toDepartmentId" class="form-label fw-bold">التخصص الجديد</label>
                                    <select wire:model.live="toDepartmentId" id="toDepartmentId" class="form-select @error('toDepartmentId') is-invalid @enderror">
                                        <option value="">اختر التخصص</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('toDepartmentId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="toSectionId" class="form-label fw-bold">الشعبة الجديدة</label>
                                    <select wire:model="toSectionId" id="toSectionId" class="form-select @error('toSectionId') is-invalid @enderror" @disabled($targetSections->isEmpty())>
                                        <option value="">اختر الشعبة</option>
                                        @foreach($targetSections as $section)
                                            <option value="{{ $section->id }}">{{ $section->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('toSectionId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label for="toLevelId" class="form-label fw-bold">الفرقة</label>
                                    <select wire:model="toLevelId" id="toLevelId" class="form-select @error('toLevelId') is-invalid @enderror">
                                        <option value="">اختر الفرقة</option>
                                        @foreach($levels as $level)
                                            <option value="{{ $level->id }}">{{ $level->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('toLevelId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-12">
                                    <label for="reason" class="form-label fw-bold">سبب التحويل <span class="text-muted fw-normal">(اختياري)</span></label>
                                    <textarea wire:model="reason" id="reason" rows="2" class="form-control" placeholder="اكتب سبب التحويل..."></textarea>
                                </div>
                            </div>

                            <div class="alert alert-warning mt-4 mb-0" role="alert">
                                <i class="ti tabler-alert-triangle me-1"></i>
                                الطلب يُنشأ بحالة «قيد المراجعة». لن يتم استرجاع أي مصاريف أو إلغاء أي مواد إلا بعد موافقة صاحب الصلاحية.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="button" class="btn btn-primary" wire:click="createRequest" @disabled(! $student)>
                            إنشاء الطلب
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endcan
</div>
