<div>
    <div class="card-header border-bottom mb-3 pb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <select wire:model.live="perPage" class="form-select form-select-sm w-auto">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-muted small">صفحة</span>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti tabler-layout-columns me-1"></i> الأعمدة
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 200px;">
                        @foreach($availableColumns as $column)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" wire:model.live="selectedColumns"
                                       value="{{ $column['key'] }}" id="col-{{ $column['key'] }}">
                                <label class="form-check-label" for="col-{{ $column['key'] }}">
                                    {{ $column['label'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button type="button" wire:click="toggleFilters" class="btn {{ $showFilters ? 'btn-label-primary' : 'btn-outline-primary' }}">
                    <i class="ti tabler-filter me-1"></i>
                    تصفية
                </button>
                <div class="input-group input-group-merge w-100" style="min-width: 300px;">
                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control"
                           placeholder="بحث باسم الطالب، كود المستخدم، الرقم القومي...">
                </div>
                <a href="{{ route('students.create') }}" class="btn btn-primary text-nowrap">
                    <i class="ti tabler-plus me-1"></i> إضافة طالب
                </a>
            </div>
        </div>

        @if($showFilters)
            <div class="mt-3 p-3 bg-light rounded border">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">التخصص</label>
                        <select wire:model.live="department_id" class="form-select form-select-sm">
                            <option value="">كل التخصصات</option>
                            @foreach($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">الشعبة</label>
                        <select wire:model.live="section_id" class="form-select form-select-sm">
                            <option value="">كل الشعب</option>
                            @foreach($this->sections as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">الفرقة</label>
                        <select wire:model.live="level_id" class="form-select form-select-sm">
                            <option value="">كل الفرق</option>
                            @foreach($this->levels as $lvl)
                                <option value="{{ $lvl->id }}">{{ $lvl->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">الجنس</label>
                        <select wire:model.live="gender" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            <option value="male">ذكر</option>
                            <option value="female">أنثى</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">الجنسية</label>
                        <select wire:model.live="nationality_id" class="form-select form-select-sm">
                            <option value="">كل الجنسيات</option>
                            @foreach($this->nationalities as $nat)
                                <option value="{{ $nat->id }}">{{ $nat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">نوع الشهادة</label>
                        <select wire:model.live="certificate_type_id" class="form-select form-select-sm">
                            <option value="">كل الشهادات</option>
                            @foreach($this->certificateTypes as $cert)
                                <option value="{{ $cert->id }}">{{ $cert->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">تصنيف التقديم</label>
                        <select wire:model.live="application_category" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            @foreach(\App\Enums\Student\ApplicationCategory::cases() as $cat)
                                <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">الحالة</label>
                        <select wire:model.live="status" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            @foreach(\App\Enums\Student\StudentStatus::cases() as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">المرشد الأكاديمي</label>
                        <select wire:model.live="academic_advisor_id" class="form-select form-select-sm">
                            <option value="">الكل</option>
                            @foreach($this->academicAdvisors as $advisor)
                                <option value="{{ $advisor->id }}">{{ $advisor->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" wire:click="resetFilters" class="btn btn-sm btn-label-secondary w-100">
                            <i class="ti tabler-refresh me-1"></i> إعادة تعيين
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead class="table-light">
            <tr>
                @if(in_array('name', $selectedColumns))
                    <th class="fw-bold">الطالب</th>
                @endif
                @if(in_array('username', $selectedColumns))
                    <th class="fw-bold">كود المستخدم</th>
                @endif
                @if(in_array('national_id', $selectedColumns))
                    <th class="fw-bold">الرقم القومي</th>
                @endif
                @if(in_array('email', $selectedColumns))
                    <th class="fw-bold">البريد الإلكتروني</th>
                @endif
                @if(in_array('phone', $selectedColumns))
                    <th class="fw-bold">رقم الهاتف</th>
                @endif
                @if(in_array('gender', $selectedColumns))
                    <th class="fw-bold">الجنس</th>
                @endif
                @if(in_array('score', $selectedColumns))
                    <th class="fw-bold">المجموع</th>
                @endif
                @if(in_array('level', $selectedColumns))
                    <th class="fw-bold">الفرقة</th>
                @endif
                @if(in_array('section', $selectedColumns))
                    <th class="fw-bold">الشعبة</th>
                @endif
                @if(in_array('academic_advisor', $selectedColumns))
                    <th class="fw-bold">المرشد الأكاديمي</th>
                @endif
                @if(in_array('status', $selectedColumns))
                    <th class="fw-bold">الحالة</th>
                @endif
                <th class="fw-bold text-center">الإجراءات</th>
            </tr>
            </thead>
            <tbody class="table-border-bottom-0">
            @forelse($students as $student)
                <tr>
                    @if(in_array('name', $selectedColumns))
                        <td>
                            <div class="d-flex justify-content-start align-items-center">
                                <a href="{{ route('students.show', $student) }}" class="avatar-wrapper me-3">
                                    <div class="avatar avatar-sm">
                                        @if($student->image)
                                            <img src="{{ asset('storage/' . $student->image) }}" alt="Avatar"
                                                 class="rounded-circle">
                                        @else
                                            <span class="avatar-initial rounded-circle bg-label-primary">
                                                    {{ mb_substr($student->name, 0, 1) }}
                                                </span>
                                        @endif
                                    </div>
                                </a>
                                <div class="d-flex flex-column">
                                    <a href="{{ route('students.show', $student) }}" class="text-heading fw-medium text-decoration-none hover-primary">
                                        {{ $student->name }}
                                    </a>
                                    @if(!in_array('email', $selectedColumns))
                                        <small class="text-muted">{{ $student->email ?? 'لا يوجد بريد' }}</small>
                                    @endif
                                </div>
                            </div>
                        </td>
                    @endif
                    @if(in_array('username', $selectedColumns))
                        <td>
                            <span class="badge bg-label-info">{{ $student->username }}</span>
                        </td>
                    @endif
                    @if(in_array('national_id', $selectedColumns))
                        <td>{{ $student->national_id }}</td>
                    @endif
                    @if(in_array('email', $selectedColumns))
                        <td>{{ $student->email ?? 'لا يوجد بريد' }}</td>
                    @endif
                    @if(in_array('phone', $selectedColumns))
                        <td>{{ $student->phone ?? 'لا يوجد هاتف' }}</td>
                    @endif
                    @if(in_array('gender', $selectedColumns))
                        <td>{{ $student->gender == 'male' ? 'ذكر' : 'أنثى' }}</td>
                    @endif
                    @if(in_array('score', $selectedColumns))
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-medium">{{ $student->score }}</span>
                                <small class="text-muted">{{ number_format(($student->score / 410) * 100, 2) }}%</small>
                            </div>
                        </td>
                    @endif
                    @if(in_array('level', $selectedColumns))
                        <td>{{ $student->level?->name }}</td>
                    @endif
                    @if(in_array('section', $selectedColumns))
                        <td>{{ $student->section?->name }}</td>
                    @endif
                    @if(in_array('academic_advisor', $selectedColumns))
                        <td>
                            @if($student->academicAdvisor)
                                <span class="badge bg-label-primary">{{ $student->academicAdvisor->name }}</span>
                            @else
                                <span class="badge bg-label-secondary">غير معين</span>
                            @endif
                        </td>
                    @endif
                    @if(in_array('status', $selectedColumns))
                        <td>
                            @php
                                $statusClass = match($student->status?->value) {
                                    'active' => 'bg-label-success',
                                    'inactive' => 'bg-label-danger',
                                    'suspended' => 'bg-label-warning',
                                    default => 'bg-label-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                    {{ $student->status?->label() ?? 'غير محدد' }}
                                </span>
                        </td>
                    @endif
                    <td class="text-center">
                        <div class="d-inline-block">
                            <a href="javascript:;" class="btn btn-sm btn-icon dropdown-toggle hide-arrow"
                               data-bs-toggle="dropdown">
                                <i class="ti tabler-dots-vertical"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="{{ route('students.show', $student) }}" class="dropdown-item">
                                    <i class="ti tabler-eye me-1"></i> عرض التفاصيل
                                </a>
                                <a href="{{ route('students.edit', $student) }}" class="dropdown-item">
                                    <i class="ti tabler-edit me-1"></i> تعديل
                                </a>
                                <button type="button" class="dropdown-item text-danger"
                                        onclick="confirmDeleteStudent({{ $student->id }}, '{{ $student->name }}')">
                                    <i class="ti tabler-trash me-1"></i> حذف
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($selectedColumns) + 1 }}" class="text-center py-5 text-muted">
                        <div class="d-flex flex-column align-items-center">
                            <i class="ti tabler-user-off d-block mb-2" style="font-size: 3rem;"></i>
                            لا يوجد طلاب مطابقين للبحث
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-footer border-top pt-3">
        {{ $students->links() }}
    </div>

    @script
    <script>
        window.confirmDeleteStudent = function (id, name) {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `سيتم حذف الطالب: ${name}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'نعم، احذف!',
                cancelButtonText: 'إلغاء',
                customClass: {
                    confirmButton: 'btn btn-primary me-1',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $wire.delete(id);
                }
            });
        }
    </script>
    @endscript
</div>
