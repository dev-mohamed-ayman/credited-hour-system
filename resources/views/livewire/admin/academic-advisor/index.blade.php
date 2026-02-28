<div>
    <div class="card">
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
                    <div class="input-group input-group-merge w-100" style="min-width: 300px;">
                        <span class="input-group-text"><i class="ti tabler-search"></i></span>
                        <input type="text" wire:model.live.debounce.300ms="search" class="form-control"
                               placeholder="بحث باسم المرشد أو كود المستخدم...">
                    </div>
                    <a href="{{ route('academic-advisors.create') }}" class="btn btn-primary text-nowrap">
                        <i class="ti tabler-plus me-1"></i> إضافة مرشد
                    </a>
                </div>
            </div>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead class="table-light">
                <tr>
                    <th class="fw-bold">المرشد الأكاديمي</th>
                    <th class="fw-bold">كود المستخدم</th>
                    <th class="fw-bold">التخصصات</th>
                    <th class="fw-bold">الشعب</th>
                    <th class="fw-bold">الفرق الدراسية</th>
                    <th class="fw-bold text-center">الطلاب (الحالي/الأقصى)</th>
                    <th class="fw-bold text-center">الحالة</th>
                    <th class="fw-bold text-center">الإجراءات</th>
                </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                @forelse($advisors as $advisor)
                    <tr>
                        <td>
                            <div class="d-flex justify-content-start align-items-center">
                                <div class="avatar-wrapper me-3">
                                    <div class="avatar avatar-sm">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            {{ mb_substr($advisor->name, 0, 1) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex flex-column">
                                    <span class="text-heading fw-medium">{{ $advisor->name }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-label-info">{{ $advisor->username }}</span>
                        </td>
                        <td>
                            @php
                                $allDepts = $advisor->assignments->pluck('department_id')->unique();
                                $hasAllDepts = $allDepts->contains(null);
                            @endphp
                            @if($hasAllDepts)
                                <span class="badge bg-label-primary">الكل</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($advisor->assignments->pluck('department.name')->unique()->filter() as $name)
                                        <span class="badge bg-label-info">{{ $name }}</span>
                                    @endforeach
                                    @if($advisor->assignments->isEmpty())
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $allSections = $advisor->assignments->pluck('section_id')->unique();
                                $hasAllSections = $allSections->contains(null);
                            @endphp
                            @if($hasAllSections)
                                <span class="badge bg-label-primary">الكل</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($advisor->assignments->pluck('section.name')->unique()->filter() as $name)
                                        <span class="badge bg-label-info">{{ $name }}</span>
                                    @endforeach
                                    @if($advisor->assignments->isEmpty())
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @php
                                $allLevels = $advisor->assignments->pluck('level_id')->unique();
                                $hasAllLevels = $allLevels->contains(null);
                            @endphp
                            @if($hasAllLevels)
                                <span class="badge bg-label-primary">الكل</span>
                            @else
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($advisor->assignments->pluck('level.name')->unique()->filter() as $name)
                                        <span class="badge bg-label-info">{{ $name }}</span>
                                    @endforeach
                                    @if($advisor->assignments->isEmpty())
                                        <span class="text-muted small">-</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex flex-column align-items-center">
                                <span class="fw-medium">{{ $advisor->current_students }} / {{ $advisor->max_students }}</span>
                                <div class="progress w-100 mt-1" style="height: 6px; min-width: 80px;">
                                    @php
                                        $percent = $advisor->max_students > 0 ? ($advisor->current_students / $advisor->max_students) * 100 : 0;
                                        $bgClass = $percent > 90 ? 'bg-danger' : ($percent > 70 ? 'bg-warning' : 'bg-success');
                                    @endphp
                                    <div class="progress-bar {{ $bgClass }}" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-flex justify-content-center">
                                <input class="form-check-input cursor-pointer" type="checkbox"
                                       wire:click="toggleStatus({{ $advisor->id }})"
                                       {{ $advisor->is_active ? 'checked' : '' }}>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="d-flex align-items-center justify-content-center">
                                <a href="{{ route('students.index', ['academic_advisor_id' => $advisor->id]) }}"
                                   class="btn btn-sm btn-icon btn-label-primary me-2" title="عرض الطلاب">
                                    <i class="ti tabler-users"></i>
                                </a>
                                <div class="d-inline-block">
                                    <a href="javascript:;" class="btn btn-sm btn-icon dropdown-toggle hide-arrow"
                                       data-bs-toggle="dropdown">
                                        <i class="ti tabler-dots-vertical"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="{{ route('academic-advisors.edit', $advisor) }}" class="dropdown-item">
                                            <i class="ti tabler-edit me-1"></i> تعديل
                                        </a>
                                        <button type="button" class="dropdown-item text-danger"
                                                onclick="confirmDelete({{ $advisor->id }}, '{{ $advisor->name }}')">
                                            <i class="ti tabler-trash me-1"></i> حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <div class="d-flex flex-column align-items-center">
                                <i class="ti tabler-user-off d-block mb-2" style="font-size: 3rem;"></i>
                                لا يوجد مرشدين أكاديميين مطابقين للبحث
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer border-top pt-3">
            {{ $advisors->links() }}
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
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
                        <p class="mb-1">هل أنت متأكد من حذف المرشد الأكاديمي: <br>
                            <strong class="text-danger" id="deleteAdvisorName"></strong>؟
                        </p>
                        <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه وسيتم حذف كافة الارتباطات الخاصة به.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">تأكيد الحذف</button>
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        let advisorIdToDelete = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));

        window.confirmDelete = function (id, name) {
            advisorIdToDelete = id;
            document.getElementById('deleteAdvisorName').textContent = name;
            deleteModal.show();
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            if (advisorIdToDelete) {
                $wire.delete(advisorIdToDelete);
                deleteModal.hide();
            }
        });
    </script>
    @endscript
</div>
