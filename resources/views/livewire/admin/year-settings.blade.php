<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">إعدادات السنوات الدراسية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">إعدادات السنوات الدراسية</li>
                </ol>
            </nav>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="ti tabler-circle-check me-2"></i>
                {{ session('message') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($years->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <div class="text-muted mb-4">
                    <i class="ti tabler-calendar-star fs-1"></i>
                </div>
                <h5 class="mb-2">لا توجد سنوات دراسية</h5>
                <p class="text-muted mb-4">يرجى إضافة سنة دراسية أولاً</p>
                <a class="btn btn-primary" href="{{ route('years.create') }}">
                    <i class="fa-solid fa-plus me-1"></i> إضافة سنة
                </a>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">السنوات الدراسية</h6>
                    </div>
                    <div class="card-body p-2">
                        <div class="list-group list-group-flush">
                            @foreach($years as $year)
                                <button type="button" 
                                        class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ $selectedYearId == $year->id ? 'active' : '' }}"
                                        wire:click="selectYear({{ $year->id }})">
                                    {{ $year->year }}
                                    @if($year->getCurrentSemester())
                                        <span class="badge bg-primary">{{ $year->getCurrentSemester()->label() }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-9">
                @if($selectedYear)
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">إعدادات السنة: {{ $selectedYear->year }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-5">
                                <h6 class="mb-3 pb-2 border-bottom">حالة الترمات</h6>
                                
                                <div class="row g-4">
                                    <!-- First Semester -->
                                    <div class="col-md-4">
                                        <div class="card border {{ $firstSemesterStatus != 'disabled' ? 'border-primary' : '' }}">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="avatar avatar-md me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="ti tabler-calendar-star fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">الترم الأول</h6>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button type="button" 
                                                            class="btn {{ $firstSemesterStatus == 'open_registration' ? 'btn-primary' : 'btn-label-primary' }} w-100"
                                                            wire:click="updateSemester('first', 'open_registration')"
                                                            @if($firstSemesterStatus == 'open_registration') disabled @endif>
                                                        <i class="ti tabler-circle-check me-2"></i>
                                                        فتح التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $firstSemesterStatus == 'closed_registration' ? 'btn-warning' : 'btn-label-warning' }} w-100"
                                                            wire:click="updateSemester('first', 'closed_registration')"
                                                            @if($firstSemesterStatus == 'closed_registration') disabled @endif>
                                                        <i class="ti tabler-lock me-2"></i>
                                                        غلق التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $firstSemesterStatus == 'disabled' ? 'btn-secondary' : 'btn-label-secondary' }} w-100"
                                                            wire:click="updateSemester('first', 'disabled')"
                                                            @if($firstSemesterStatus == 'disabled') disabled @endif>
                                                        <i class="ti tabler-ban me-2"></i>
                                                        تعطيل
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Second Semester -->
                                    <div class="col-md-4">
                                        <div class="card border {{ $secondSemesterStatus != 'disabled' ? 'border-primary' : '' }}">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="avatar avatar-md me-3">
                                                        <span class="avatar-initial rounded bg-label-success">
                                                            <i class="ti tabler-calendar-event fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">الترم الثاني</h6>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button type="button" 
                                                            class="btn {{ $secondSemesterStatus == 'open_registration' ? 'btn-success' : 'btn-label-success' }} w-100"
                                                            wire:click="updateSemester('second', 'open_registration')"
                                                            @if($secondSemesterStatus == 'open_registration') disabled @endif>
                                                        <i class="ti tabler-circle-check me-2"></i>
                                                        فتح التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $secondSemesterStatus == 'closed_registration' ? 'btn-warning' : 'btn-label-warning' }} w-100"
                                                            wire:click="updateSemester('second', 'closed_registration')"
                                                            @if($secondSemesterStatus == 'closed_registration') disabled @endif>
                                                        <i class="ti tabler-lock me-2"></i>
                                                        غلق التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $secondSemesterStatus == 'disabled' ? 'btn-secondary' : 'btn-label-secondary' }} w-100"
                                                            wire:click="updateSemester('second', 'disabled')"
                                                            @if($secondSemesterStatus == 'disabled') disabled @endif>
                                                        <i class="ti tabler-ban me-2"></i>
                                                        تعطيل
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Summer Semester -->
                                    <div class="col-md-4">
                                        <div class="card border {{ $summerSemesterStatus != 'disabled' ? 'border-primary' : '' }}">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="avatar avatar-md me-3">
                                                        <span class="avatar-initial rounded bg-label-warning">
                                                            <i class="ti tabler-sun fs-4"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">الترم الصيفي</h6>
                                                    </div>
                                                </div>
                                                
                                                <div class="d-grid gap-2">
                                                    <button type="button" 
                                                            class="btn {{ $summerSemesterStatus == 'open_registration' ? 'btn-warning' : 'btn-label-warning' }} w-100"
                                                            wire:click="updateSemester('summer', 'open_registration')"
                                                            @if($summerSemesterStatus == 'open_registration') disabled @endif>
                                                        <i class="ti tabler-circle-check me-2"></i>
                                                        فتح التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $summerSemesterStatus == 'closed_registration' ? 'btn-warning' : 'btn-label-warning' }} w-100"
                                                            wire:click="updateSemester('summer', 'closed_registration')"
                                                            @if($summerSemesterStatus == 'closed_registration') disabled @endif>
                                                        <i class="ti tabler-lock me-2"></i>
                                                        غلق التسجيل
                                                    </button>
                                                    <button type="button" 
                                                            class="btn {{ $summerSemesterStatus == 'disabled' ? 'btn-secondary' : 'btn-label-secondary' }} w-100"
                                                            wire:click="updateSemester('summer', 'disabled')"
                                                            @if($summerSemesterStatus == 'disabled') disabled @endif>
                                                        <i class="ti tabler-ban me-2"></i>
                                                        تعطيل
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h6 class="mb-3 pb-2 border-bottom">إرشاد الأكاديمي</h6>
                                
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar avatar-md me-3">
                                                <span class="avatar-initial rounded bg-label-info">
                                                    <i class="ti tabler-book-2 fs-4"></i>
                                                </span>
                                            </div>
                                            <div>
                                                <h6 class="mb-0">تسجيل الإرشاد الأكاديمي</h6>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button type="button" 
                                                    class="btn {{ $academicAdvisingStatus == 'open' ? 'btn-info' : 'btn-label-info' }}"
                                                    wire:click="updateAcademicAdvising('open')"
                                                    @if($academicAdvisingStatus == 'open') disabled @endif>
                                                <i class="ti tabler-circle-check me-2"></i>
                                                فتح التسجيل
                                            </button>
                                            <button type="button" 
                                                    class="btn {{ $academicAdvisingStatus == 'closed' ? 'btn-secondary' : 'btn-label-secondary' }}"
                                                    wire:click="updateAcademicAdvising('closed')"
                                                    @if($academicAdvisingStatus == 'closed') disabled @endif>
                                                <i class="ti tabler-lock me-2"></i>
                                                غلق التسجيل
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
