<div>
    <form wire:submit.prevent="save">
        <div class="card-body row">
            <!-- Name & Hours -->
            <div class="form-group col-md-8 mb-4">
                <label for="name" class="form-label fw-bold">اسم المادة</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti tabler-book"></i></span>
                    <input type="text" wire:model="name" id="name"
                           class="form-control @error('name') is-invalid @enderror" 
                           placeholder="مثال: رياضيات هندسية">
                </div>
                @error('name')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-4 mb-4">
                <label for="hours" class="form-label fw-bold">عدد الساعات</label>
                <div class="input-group input-group-merge">
                    <span class="input-group-text"><i class="ti tabler-clock"></i></span>
                    <input type="number" wire:model="hours" id="hours"
                           class="form-control @error('hours') is-invalid @enderror" 
                           placeholder="مثال: 3">
                </div>
                @error('hours')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Department & Semester -->
            <div class="form-group col-md-6 mb-4">
                <label for="department_id" class="form-label fw-bold">التخصص</label>
                <select wire:model.live="department_id" id="department_id" 
                        class="form-select @error('department_id') is-invalid @enderror">
                    <option value="">اختر التخصص</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }} ({{ $department->code }})</option>
                    @endforeach
                </select>
                @error('department_id')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group col-md-6 mb-4">
                <label for="semester" class="form-label fw-bold">الفصل الدراسي</label>
                <select wire:model="semester" id="semester" 
                        class="form-select @error('semester') is-invalid @enderror">
                    <option value="">اختر الفصل الدراسي</option>
                    <option value="الأول">الأول</option>
                    <option value="الثاني">الثاني</option>
                    <option value="الصيفي">الصيفي</option>
                </select>
                @error('semester')
                <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <!-- Sections -->
            <div class="form-group col-12 mb-4">
                <label class="form-label fw-bold d-block mb-2">
                    اختيار الشعب المرتبطة 
                    @if($department_id) 
                        <span class="badge bg-label-primary ms-2">شعب تخصص {{ $departments->find($department_id)->name }}</span>
                    @endif
                </label>
                
                @if($department_id)
                    <div class="row g-3">
                        @forelse($sections as $section)
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check custom-option custom-option-basic h-100 {{ in_array($section->id, $section_ids) ? 'checked' : '' }}">
                                    <label class="form-check-label custom-option-content" for="section_{{ $section->id }}">
                                        <input class="form-check-input" type="checkbox" wire:model="section_ids"
                                               value="{{ $section->id }}" id="section_{{ $section->id }}">
                                        <span class="custom-option-header">
                                            <span class="h6 mb-0">{{ $section->name }}</span>
                                            <i class="ti tabler-circle-check text-primary"></i>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-3 bg-lighter rounded">
                                <i class="ti tabler-alert-circle text-warning fs-3 mb-2"></i>
                                <p class="mb-0">لا توجد شعب لهذا التخصص حالياً</p>
                            </div>
                        @endforelse
                    </div>
                    @error('section_ids')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                @else
                    <div class="alert alert-info d-flex align-items-center mb-0" role="alert">
                        <span class="alert-icon text-info me-2">
                            <i class="ti tabler-info-circle ti-xs"></i>
                        </span>
                        برجاء اختيار التخصص أولاً لعرض الشعب المتاحة
                    </div>
                @endif
            </div>

            <!-- Status Toggles -->
            <div class="col-md-6 mb-4 d-flex gap-4">
                <div class="form-check form-switch custom-switch-primary">
                    <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                    <label class="form-check-label fw-medium" for="is_active">تفعيل المادة</label>
                </div>
                <div class="form-check form-switch custom-switch-info">
                    <input class="form-check-input" type="checkbox" wire:model="is_selected" id="is_selected">
                    <label class="form-check-label fw-medium" for="is_selected">مادة اختيارية</label>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="card-footer d-flex justify-content-end gap-2 border-top pt-4">
            <a href="{{ route('courses.index') }}" class="btn btn-label-secondary waves-effect">
                <i class="ti tabler-arrow-left me-1"></i> إلغاء
            </a>
            <button type="submit" class="btn btn-primary waves-effect waves-light" wire:loading.attr="disabled">
                <span wire:loading.remove>
                    <i class="ti tabler-device-floppy me-1"></i> حفظ المادة
                </span>
                <span wire:loading>
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    جاري الحفظ...
                </span>
            </button>
        </div>
    </form>
</div>
