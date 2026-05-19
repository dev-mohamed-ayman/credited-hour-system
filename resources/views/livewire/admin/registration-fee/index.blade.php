<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">مصاريف التسجيل</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">مصاريف التسجيل</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ti tabler-circle-check me-2"></i>
                        {{ session('message') }}
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="card overflow-hidden">
                <div class="card-header p-0">
                    <div class="nav-align-top">
                        <ul class="nav nav-tabs border-bottom-0" role="tablist">
                            @foreach($departments as $dept)
                                <li class="nav-item">
                                    <button type="button" 
                                            class="nav-link py-3 {{ $activeDepartmentId == $dept->id ? 'active' : '' }}" 
                                            wire:click="setDepartment({{ $dept->id }})">
                                        <i class="ti tabler-layout-grid me-1"></i>
                                        {{ $dept->name }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="card-body pt-4">
                    <div class="row">
                        <div class="col-md-3 border-end">
                            <div class="nav-align-left">
                                <ul class="nav nav-pills flex-column" role="tablist">
                                    @foreach($levels as $lvl)
                                        <li class="nav-item mb-1">
                                            <button type="button" 
                                                    class="nav-link w-100 text-start {{ $activeLevelId == $lvl->id ? 'active' : '' }}" 
                                                    wire:click="setLevel({{ $lvl->id }})">
                                                <i class="ti tabler-stairs me-2"></i>
                                                {{ $lvl->name }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="ps-md-4">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="avatar avatar-md me-3">
                                        <span class="avatar-initial rounded bg-label-primary">
                                            <i class="ti tabler-coin fs-4"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">إعدادات المصاريف</h5>
                                        <small class="text-muted">تعديل بيانات المصاريف لـ {{ $departments->find($activeDepartmentId)?->name }} - {{ $levels->find($activeLevelId)?->name }}</small>
                                    </div>
                                </div>

                                <form wire:submit.prevent="save">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">سعر الساعة (Hour Payment)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-clock-dollar"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('hour_payment') is-invalid @enderror" wire:model="hour_payment">
                                            </div>
                                            @error('hour_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">المصاريف الوزارية (Ministerial Payment)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-building-bank"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('ministerial_payment') is-invalid @enderror" wire:model="ministerial_payment">
                                            </div>
                                            @error('ministerial_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">سعر الساعة للطلاب الباقين (Remaining Hour Payment)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-clock-exclamation"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('hour_payment_remaining') is-invalid @enderror" wire:model="hour_payment_remaining">
                                            </div>
                                            @error('hour_payment_remaining') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">المصاريف الوزارية للطلاب الباقين (Remaining Ministerial Payment)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-building-exclamation"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('ministerial_payment_remaining') is-invalid @enderror" wire:model="ministerial_payment_remaining">
                                            </div>
                                            @error('ministerial_payment_remaining') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">إجمالي مصاريف الطالب (Total Student Payment)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-sum"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('total_student_payment') is-invalid @enderror" wire:model="total_student_payment">
                                            </div>
                                            @error('total_student_payment') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">ساعات تسجيل الطالب (Registration Hours)</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="ti tabler-calendar-time"></i></span>
                                                <input type="number" step="0.01" class="form-control @error('student_registration_hour') is-invalid @enderror" wire:model="student_registration_hour">
                                            </div>
                                            @error('student_registration_hour') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-medium text-primary">عدد الطلاب في السكشن (Students per Section)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-label-primary border-primary"><i class="ti tabler-users-group text-primary"></i></span>
                                                <input type="number" class="form-control border-primary @error('number_of_students_per_section') is-invalid @enderror" wire:model="number_of_students_per_section">
                                            </div>
                                            @error('number_of_students_per_section') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-5 d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary btn-lg shadow-sm" wire:loading.attr="disabled">
                                            <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-2" role="status"></span>
                                            <i wire:loading.remove wire:target="save" class="ti tabler-device-floppy me-2"></i>
                                            حفظ التغييرات
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
