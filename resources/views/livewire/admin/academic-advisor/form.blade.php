<div>
    <form wire:submit="save">
        <div class="row g-4">
            <!-- Advisor Basic Info -->
            <div class="col-12 col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">المعلومات الأساسية</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="advisor-name">اسم المرشد</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="advisor-name" wire:model="name" placeholder="أدخل اسم المرشد">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="advisor-username">كود المستخدم</label>
                            <input type="text" class="form-control @error('username') is-invalid @enderror" id="advisor-username" wire:model="username" placeholder="أدخل كود المستخدم">
                            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="max-students">الحد الأقصى للطلاب</label>
                            <input type="number" class="form-control @error('max_students') is-invalid @enderror" id="max-students" wire:model="max_students">
                            @error('max_students') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignments -->
            <div class="col-12 col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">تعيينات التخصصات والشعب والفرق الدراسية</h5>
                    </div>
                    <div class="card-body">
                        <!-- Departments -->
                        <div class="mb-4">
                            <label class="form-label d-block fw-bold mb-2">التخصصات</label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-check custom-option custom-option-basic">
                                        <label class="form-check-label custom-option-content" for="dept_all">
                                            <input class="form-check-input" type="checkbox" wire:model.live="selectedDepartments" value="all" id="dept_all">
                                            <span class="custom-option-header">
                                                <span class="h6 mb-0">الكل</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                @foreach($this->departments as $dept)
                                    <div class="col-md-3">
                                        <div class="form-check custom-option custom-option-basic">
                                            <label class="form-check-label custom-option-content" for="dept_{{ $dept->id }}">
                                                <input class="form-check-input" type="checkbox" wire:model.live="selectedDepartments" value="{{ $dept->id }}" id="dept_{{ $dept->id }}" {{ in_array('all', $selectedDepartments) ? 'disabled' : '' }}>
                                                <span class="custom-option-header">
                                                    <span class="h6 mb-0">{{ $dept->name }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedDepartments') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Sections -->
                        <div class="mb-4">
                            <label class="form-label d-block fw-bold mb-2">الشعب</label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-check custom-option custom-option-basic">
                                        <label class="form-check-label custom-option-content" for="sect_all">
                                            <input class="form-check-input" type="checkbox" wire:model.live="selectedSections" value="all" id="sect_all" {{ empty($selectedDepartments) ? 'disabled' : '' }}>
                                            <span class="custom-option-header">
                                                <span class="h6 mb-0">الكل</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                @foreach($this->availableSections as $sect)
                                    <div class="col-md-3">
                                        <div class="form-check custom-option custom-option-basic">
                                            <label class="form-check-label custom-option-content" for="sect_{{ $sect->id }}">
                                                <input class="form-check-input" type="checkbox" wire:model.live="selectedSections" value="{{ $sect->id }}" id="sect_{{ $sect->id }}" {{ in_array('all', $selectedSections) ? 'disabled' : '' }}>
                                                <span class="custom-option-header">
                                                    <span class="h6 mb-0">{{ $sect->name }}</span>
                                                </span>
                                                <span class="custom-option-body">
                                                    <small class="text-muted">{{ $sect->department->name }}</small>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedSections') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <!-- Levels -->
                        <div class="mb-4">
                            <label class="form-label d-block fw-bold mb-2">الفرق الدراسية</label>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="form-check custom-option custom-option-basic">
                                        <label class="form-check-label custom-option-content" for="lvl_all">
                                            <input class="form-check-input" type="checkbox" wire:model.live="selectedLevels" value="all" id="lvl_all" {{ empty($selectedSections) ? 'disabled' : '' }}>
                                            <span class="custom-option-header">
                                                <span class="h6 mb-0">الكل</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                @foreach($this->availableLevels as $lvl)
                                    <div class="col-md-3">
                                        <div class="form-check custom-option custom-option-basic">
                                            <label class="form-check-label custom-option-content" for="lvl_{{ $lvl->id }}">
                                                <input class="form-check-input" type="checkbox" wire:model.live="selectedLevels" value="{{ $lvl->id }}" id="lvl_{{ $lvl->id }}" {{ in_array('all', $selectedLevels) ? 'disabled' : '' }}>
                                                <span class="custom-option-header">
                                                    <span class="h6 mb-0">{{ $lvl->name }}</span>
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedLevels') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <a href="{{ route('academic-advisors.index') }}" class="btn btn-label-secondary">إلغاء</a>
                    <button type="submit" class="btn btn-primary">حفظ المرشد الأكاديمي</button>
                </div>
            </div>
        </div>
    </form>
</div>
