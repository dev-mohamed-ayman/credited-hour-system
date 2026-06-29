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
                @foreach($availableColumns as $col)
                    @if(in_array($col['key'], $selectedColumns))
                        @if(in_array($col['key'], ['name', 'username', 'score']))
                            <th class="fw-bold cursor-pointer select-none" wire:click="sortBy('{{ $col['key'] === 'score' ? 'score' : ($col['key'] === 'username' ? 'username' : 'name') }}')"
                                style="cursor: pointer;">
                                {{ $col['label'] }}
                                @if($sortField === ($col['key'] === 'score' ? 'score' : ($col['key'] === 'username' ? 'username' : 'name')))
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                        @else
                            <th class="fw-bold">{{ $col['label'] }}</th>
                        @endif
                    @endif
                @endforeach
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
                                    'registered' => 'bg-label-success',
                                    'excused' => 'bg-label-info',
                                    'suspended' => 'bg-label-warning',
                                    'withdrawn' => 'bg-label-secondary',
                                    'dismissed' => 'bg-label-danger',
                                    'graduated' => 'bg-label-primary',
                                    default => 'bg-label-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                    {{ $student->status?->label() ?? 'غير محدد' }}
                                </span>
                        </td>
                    @endif
                    @if(in_array('plain_password', $selectedColumns))
                        <td><code class="text-dark">{{ $student->plain_password }}</code></td>
                    @endif
                    @if(in_array('national_id_place', $selectedColumns))
                        <td>{{ $student->national_id_place }}</td>
                    @endif
                    @if(in_array('nationality', $selectedColumns))
                        <td>{{ $student->nationality?->name }}</td>
                    @endif
                    @if(in_array('country', $selectedColumns))
                        <td>{{ $student->country?->name }}</td>
                    @endif
                    @if(in_array('city', $selectedColumns))
                        <td>{{ $student->city?->name }}</td>
                    @endif
                    @if(in_array('birth_date', $selectedColumns))
                        <td>{{ $student->birth_date ? \Carbon\Carbon::parse($student->birth_date)->format('Y-m-d') : '-' }}</td>
                    @endif
                    @if(in_array('certificate_type', $selectedColumns))
                        <td>{{ $student->certificateType?->name }}</td>
                    @endif
                    @if(in_array('seat_number', $selectedColumns))
                        <td>{{ $student->seat_number }}</td>
                    @endif
                    @if(in_array('student_scores', $selectedColumns))
                        <td>{{ $student->scores->pluck('score')->join(', ') ?: '-' }}</td>
                    @endif
                    @if(in_array('total_score', $selectedColumns))
                        <td>{{ $student->certificateType?->total_score ?? '-' }}</td>
                    @endif
                    @if(in_array('english_score', $selectedColumns))
                        <td>{{ $student->scores->where('requirement.subject_name', 'اللغة الإنجليزية')->first()?->score ?? '-' }}</td>
                    @endif
                    @if(in_array('graduation_date', $selectedColumns))
                        <td>{{ $student->graduation_date ? \Carbon\Carbon::parse($student->graduation_date)->format('Y-m-d') : '-' }}</td>
                    @endif
                    @if(in_array('enrollment_date', $selectedColumns))
                        <td>{{ $student->created_at ? $student->created_at->format('Y-m-d') : '-' }}</td>
                    @endif
                    @if(in_array('application_category', $selectedColumns))
                        <td>{{ $student->application_category?->label() ?? '-' }}</td>
                    @endif
                    @if(in_array('religion', $selectedColumns))
                        <td>{{ $student->religion ?? '-' }}</td>
                    @endif
                    @if(in_array('address', $selectedColumns))
                        <td>{{ $student->address ?? '-' }}</td>
                    @endif
                    @if(in_array('landline_phone', $selectedColumns))
                        <td>{{ $student->landline_phone ?? '-' }}</td>
                    @endif
                    @if(in_array('guardian_job', $selectedColumns))
                        <td>{{ $student->guardian_job ?? '-' }}</td>
                    @endif
                    @if(in_array('guardian_phone_1', $selectedColumns))
                        <td>{{ $student->guardian_phone_1 ?? '-' }}</td>
                    @endif
                    @if(in_array('guardian_phone_2', $selectedColumns))
                        <td>{{ $student->guardian_phone_2 ?? '-' }}</td>
                    @endif
                    @if(in_array('study_status', $selectedColumns))
                        <td>{{ $student->study_status?->label() ?? '-' }}</td>
                    @endif
                    @if(in_array('department', $selectedColumns))
                        <td>{{ $student->section?->department?->name ?? '-' }}</td>
                    @endif
                    @if(in_array('is_foreign', $selectedColumns))
                        <td>
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input cursor-pointer" type="checkbox"
                                       wire:click="toggleBoolean({{ $student->id }}, 'is_foreign')"
                                       wire:loading.attr="disabled"
                                       wire:target="toggleBoolean({{ $student->id }}, 'is_foreign')"
                                       {{ $student->is_foreign ? 'checked' : '' }}>
                            </div>
                        </td>
                    @endif
                    @if(in_array('status_notes', $selectedColumns))
                        <td>{{ \Illuminate\Support\Str::limit($student->status_notes, 30, '...') ?: '-' }}</td>
                    @endif
                    @if(in_array('military_education_passed', $selectedColumns))
                        <td>
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input cursor-pointer" type="checkbox"
                                       wire:click="toggleBoolean({{ $student->id }}, 'military_education_passed')"
                                       wire:loading.attr="disabled"
                                       wire:target="toggleBoolean({{ $student->id }}, 'military_education_passed')"
                                       {{ $student->military_education_passed ? 'checked' : '' }}>
                            </div>
                        </td>
                    @endif
                    @if(in_array('year', $selectedColumns))
                        <td>{{ $student->year?->year ?? '-' }}</td>
                    @endif
                    @if(in_array('semester', $selectedColumns))
                        <td>{{ $student->semester?->label() ?? '-' }}</td>
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

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تأكيد الحذف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="ti tabler-alert-triangle text-warning" style="font-size: 4rem;"></i>
                    </div>
                    <div class="text-center">
                        <p class="mb-1">هل أنت متأكد من حذف الطالب: <br>
                            <strong class="text-danger" id="deleteStudentName"></strong>؟
                        </p>
                        <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه وسيتم حذف كافة بيانات الطالب.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteStudentBtn">تأكيد الحذف</button>
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        let studentIdToDelete = null;
        const deleteStudentModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));

        window.confirmDeleteStudent = function (id, name) {
            studentIdToDelete = id;
            document.getElementById('deleteStudentName').textContent = name;
            deleteStudentModal.show();
        }

        document.getElementById('confirmDeleteStudentBtn').addEventListener('click', function () {
            if (studentIdToDelete) {
                $wire.delete(studentIdToDelete);
                deleteStudentModal.hide();
            }
        });
    </script>
    @endscript
</div>
