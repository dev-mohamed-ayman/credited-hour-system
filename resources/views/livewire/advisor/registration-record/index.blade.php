<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">سجلات التسجيل</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
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
                <div class="col-md-4">
                    <label for="searchStudent" class="form-label fw-bold">اسم أو كود الطالب</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="searchStudent" id="searchStudent" class="form-control" placeholder="بحث...">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="searchYear" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="searchYear" id="searchYear" class="form-select">
                        <option value="">الكل</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
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
                        <th>الطالب</th>
                        <th>الفرقة</th>
                        <th>السنة / الترم</th>
                        <th class="text-center">عدد المواد</th>
                        <th class="text-center">تاريخ التسجيل</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($registrations as $registration)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-3">
                                        <span class="avatar-initial rounded-circle bg-label-primary"><i class="ti tabler-user"></i></span>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 text-truncate" style="max-width: 200px;" title="{{ $registration->student->name }}">{{ $registration->student->name }}</h6>
                                        <small class="text-muted">{{ $registration->student->username }}</small>
                                    </div>
                                </div>
                            </td>
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
                                {{ $registration->created_at->format('Y-m-d') }}
                            </td>
                            <td class="text-center">
                                <a href="{{ route('advisor.registration-records.show', $registration->id) }}" class="btn btn-sm btn-icon item-edit" title="عرض السجل">
                                    <i class="ti tabler-eye icon-base"></i>
                                </a>
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
</div>
