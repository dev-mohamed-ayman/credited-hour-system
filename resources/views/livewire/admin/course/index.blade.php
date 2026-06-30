<div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
        <h5 class="mb-0">قائمة المواد الدراسية</h5>
        <a class="btn btn-primary waves-effect waves-light" href="{{ route('courses.create') }}">
            <i class="fa-solid fa-plus me-1"></i> إضافة مادة
        </a>
    </div>

    <!-- Filters Section -->
    <div class="card-body border-bottom pt-3 pb-3">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">بحث</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti tabler-search"></i></span>
                    <input type="text" wire:model.live.debounce.300ms="search" class="form-control" placeholder="بحث باسم المادة أو الكود...">
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">التخصص</label>
                <select wire:model.live="department_id" class="form-select">
                    <option value="">الكل</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">الفرقة الدراسية</label>
                <select wire:model.live="level_id" class="form-select">
                    <option value="">الكل</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">الفصل الدراسي</label>
                <select wire:model.live="semester" class="form-select">
                    <option value="">الكل</option>
                    <option value="الأول">الأول</option>
                    <option value="الثاني">الثاني</option>
                    <option value="الصيفي">الصيفي</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">حالة التفعيل / اختياري</label>
                <div class="d-flex gap-2">
                    <select wire:model.live="is_active" class="form-select">
                        <option value="">التفعيل (الكل)</option>
                        <option value="1">مفعلة</option>
                        <option value="0">معطلة</option>
                    </select>
                    <select wire:model.live="is_selected" class="form-select">
                        <option value="">اختياري (الكل)</option>
                        <option value="1">نعم</option>
                        <option value="0">لا</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12 text-end">
                <button type="button" class="btn btn-label-secondary" wire:click="resetFilters">
                    <i class="ti tabler-refresh me-1"></i> إعادة ضبط الفلاتر
                </button>
            </div>
        </div>
    </div>

    <!-- Table Section -->
    <div class="table-responsive text-nowrap">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th class="text-center" style="width: 50px;">#</th>
                    <th>كود المادة</th>
                    <th>اسم المادة</th>
                    <th>الساعات</th>
                    <th>التخصص</th>
                    <th>الترم</th>
                    <th class="text-center">اختيارية</th>
                    <th class="text-center">الحالة</th>
                    <th class="text-center">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="table-border-bottom-0">
                @forelse($courses as $course)
                    <tr wire:key="course-{{ $course->id }}">
                        <td class="text-center">{{ $loop->iteration + $courses->firstItem() - 1 }}</td>
                        <td>
                            <span class="badge bg-label-secondary fw-bold text-uppercase">{{ $course->code }}</span>
                        </td>
                        <td>
                            <span class="fw-bold text-primary">{{ $course->name }}</span>
                        </td>
                        <td>
                            <span class="badge bg-label-info">{{ $course->hours }} ساعة</span>
                        </td>
                        <td>
                            <span class="badge bg-label-dark">{{ $course->department->name }}</span>
                        </td>
                        <td>
                            <span class="badge bg-label-warning">{{ $course->semester }}</span>
                        </td>
                        <td class="text-center">
                            <label class="switch switch-success">
                                <input type="checkbox" class="switch-input" 
                                       wire:click="toggleSelected({{ $course->id }})" 
                                       @if($course->is_selected) checked @endif />
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                            </label>
                        </td>
                        <td class="text-center">
                            <label class="switch switch-primary">
                                <input type="checkbox" class="switch-input" 
                                       wire:click="toggleActive({{ $course->id }})" 
                                       @if($course->is_active) checked @endif />
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                            </label>
                        </td>
                        <td class="text-center">
                            <a class="btn btn-sm btn-success" href="{{ route('courses.edit', $course->id) }}">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal"
                                data-bs-target="#deleteModal{{ $course->id }}">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Modal Delete -->
                    <div class="modal fade" id="deleteModal{{ $course->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">تأكيد الحذف</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="{{ route('courses.destroy', $course->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <div class="modal-body">
                                        <div class="text-center mb-3">
                                            <i class="fa-solid fa-triangle-exclamation text-warning fs-1"></i>
                                        </div>
                                        <div class="text-center">
                                            <p>هل أنت متأكد من حذف المادة: <br>
                                                <strong class="text-danger">{{ $course->name }}</strong>؟
                                            </p>
                                            <small class="text-muted">هذا الإجراء لا يمكن التراجع عنه.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer text-end">
                                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                                        <button type="submit" class="btn btn-danger">تأكيد الحذف</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="text-muted">لا توجد مواد دراسية مضافة حالياً</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($courses->hasPages())
        <div class="card-footer d-flex justify-content-center pb-0">
            {{ $courses->links() }}
        </div>
    @endif
</div>
