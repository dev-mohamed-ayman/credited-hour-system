<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">المصاريف الإضافية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">المصاريف الإضافية</li>
                </ol>
            </nav>
        </div>
        @if(!$showForm)
            <button class="btn btn-primary" wire:click="create">
                <i class="ti tabler-plus me-1"></i>
                إضافة مصروف جديد
            </button>
        @endif
    </div>



    <div class="row">
        @if($showForm)
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $editingFeeId ? 'تعديل مصروف' : 'إضافة مصروف جديد' }}</h5>
                        <button class="btn-close" wire:click="resetForm"></button>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="save">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">اسم المصروف</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        wire:model="name" placeholder="مثلاً: خدمات عامة، كتب، ...">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">المبلغ الإجمالي</label>
                                    <div class="input-group">
                                        <span class="input-group-text">LE</span>
                                        <input type="number" step="0.01"
                                            class="form-control @error('amount') is-invalid @enderror" wire:model="amount"
                                            {{ count($items) > 0 ? 'readonly bg-light' : '' }}>
                                    </div>
                                    @if(count($items) > 0)
                                        <small class="text-info">يتم حسابه تلقائياً من البنود بالأسفل</small>
                                    @endif
                                    @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الجنس</label>
                                    <select class="form-select @error('gender') is-invalid @enderror" wire:model="gender">
                                        <option value="both">الكل (ذكور وإناث)</option>
                                        <option value="male">ذكور فقط</option>
                                        <option value="female">إناث فقط</option>
                                    </select>
                                    @error('gender') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">تكرار الدفع</label>
                                    <div class="d-flex gap-3 mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" value="1" id="one_time_yes"
                                                wire:model="is_one_time">
                                            <label class="form-check-label" for="one_time_yes">مرة واحدة فقط</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" value="0" id="one_time_no"
                                                wire:model="is_one_time">
                                            <label class="form-check-label" for="one_time_no">يتكرر</label>
                                        </div>
                                    </div>
                                    @error('is_one_time') <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">الترم</label>
                                    <select class="form-select @error('semester') is-invalid @enderror" wire:model="semester">
                                        <option value="">اختر الترم</option>
                                        @foreach($semesters as $semester)
                                            <option value="{{ $semester->value }}">{{ $semester->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                {{-- Fee Items Section --}}
                                <div class="col-12">
                                    <div class="card border shadow-none bg-light-subtle">
                                        <div
                                            class="card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                                            <span class="fw-bold"><i
                                                    class="ti tabler-list-details me-2 text-primary"></i>بنود المصروف
                                                (اختياري)</span>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                wire:click="addItem">
                                                <i class="ti tabler-plus me-1"></i> إضافة بند
                                            </button>
                                        </div>
                                        <div class="card-body p-3">
                                            @if(count($items) > 0)
                                                @foreach($items as $index => $item)
                                                    <div class="row g-2 mb-2 align-items-center">
                                                        <div class="col-7">
                                                            <input type="text"
                                                                class="form-control form-control-sm @error('items.' . $index . '.name') is-invalid @enderror"
                                                                wire:model.live="items.{{ $index }}.name"
                                                                placeholder="اسم البند (مثلاً: كتب، كارنيه...)">
                                                            @error('items.' . $index . '.name') <div class="invalid-feedback">
                                                            {{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-4">
                                                            <div class="input-group input-group-sm">
                                                                <input type="number" step="0.01"
                                                                    class="form-control @error('items.' . $index . '.amount') is-invalid @enderror"
                                                                    wire:model.live="items.{{ $index }}.amount"
                                                                    placeholder="المبلغ">
                                                                <span class="input-group-text">LE</span>
                                                            </div>
                                                            @error('items.' . $index . '.amount') <div class="invalid-feedback">
                                                            {{ $message }}</div> @enderror
                                                        </div>
                                                        <div class="col-1 text-end">
                                                            <button type="button" class="btn btn-sm btn-icon text-danger"
                                                                wire:click="removeItem({{ $index }})">
                                                                <i class="ti tabler-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="text-center py-3 text-muted small">
                                                    لا توجد بنود مضافة. سيتم استخدام المبلغ الإجمالي المدخل أعلاه.
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="col-12">
                                    <div class="d-flex align-items-center mb-3">
                                        <i class="ti tabler-target text-primary fs-4 me-2"></i>
                                        <h6 class="fw-bold mb-0">تخصيص المصروف (Targeting)</h6>
                                    </div>
                                    <div class="row g-4">
                                        {{-- Departments --}}
                                        <div class="col-md-4">
                                            <div class="card h-100 border shadow-none bg-light-subtle">
                                                <div
                                                    class="card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                                                    <span class="fw-medium text-dark"><i
                                                            class="ti tabler-layout-grid me-2 text-primary"></i>التخصصات</span>
                                                    <button type="button" class="btn btn-xs btn-outline-primary py-0 px-2"
                                                        wire:click="selectAllDepartments">
                                                        {{ count($selectedDepartments) === count($departments) ? 'إلغاء التحديد' : 'تحديد الكل' }}
                                                    </button>
                                                </div>
                                                <div class="card-body p-3" style="max-height: 250px; overflow-y: auto;">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @foreach($departments as $dept)
                                                            <div class="target-item">
                                                                <input type="checkbox" class="btn-check"
                                                                    id="dept_{{ $dept->id }}" value="{{ $dept->id }}"
                                                                    wire:model="selectedDepartments">
                                                                <label
                                                                    class="btn btn-outline-secondary btn-sm rounded-pill px-3"
                                                                    for="dept_{{ $dept->id }}">
                                                                    {{ $dept->name }}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Levels --}}
                                        <div class="col-md-4">
                                            <div class="card h-100 border shadow-none bg-light-subtle">
                                                <div
                                                    class="card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                                                    <span class="fw-medium text-dark"><i
                                                            class="ti tabler-stairs me-2 text-info"></i>الفرق</span>
                                                    <button type="button" class="btn btn-xs btn-outline-info py-0 px-2"
                                                        wire:click="selectAllLevels">
                                                        {{ count($selectedLevels) === count($levels) ? 'إلغاء التحديد' : 'تحديد الكل' }}
                                                    </button>
                                                </div>
                                                <div class="card-body p-3" style="max-height: 250px; overflow-y: auto;">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @foreach($levels as $lvl)
                                                            <div class="target-item">
                                                                <input type="checkbox" class="btn-check" id="lvl_{{ $lvl->id }}"
                                                                    value="{{ $lvl->id }}" wire:model="selectedLevels">
                                                                <label
                                                                    class="btn btn-outline-secondary btn-sm rounded-pill px-3"
                                                                    for="lvl_{{ $lvl->id }}">
                                                                    {{ $lvl->name }}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Sections --}}
                                        <div class="col-md-4">
                                            <div class="card h-100 border shadow-none bg-light-subtle">
                                                <div
                                                    class="card-header d-flex justify-content-between align-items-center p-3 border-bottom bg-white">
                                                    <span class="fw-medium text-dark"><i
                                                            class="ti tabler-subtask me-2 text-warning"></i>الشعب</span>
                                                    <button type="button" class="btn btn-xs btn-outline-warning py-0 px-2"
                                                        wire:click="selectAllSections">
                                                        {{ count($selectedSections) === count($sections) ? 'إلغاء التحديد' : 'تحديد الكل' }}
                                                    </button>
                                                </div>
                                                <div class="card-body p-3" style="max-height: 250px; overflow-y: auto;">
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @foreach($sections as $sec)
                                                            <div class="target-item">
                                                                <input type="checkbox" class="btn-check" id="sec_{{ $sec->id }}"
                                                                    value="{{ $sec->id }}" wire:model="selectedSections">
                                                                <label
                                                                    class="btn btn-outline-secondary btn-sm rounded-pill px-3"
                                                                    for="sec_{{ $sec->id }}">
                                                                    {{ $sec->name }}
                                                                </label>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <style>
                                        .btn-check:checked+.btn-outline-primary {
                                            background-color: var(--bs-primary);
                                            color: white;
                                            border-color: var(--bs-primary);
                                        }

                                        .btn-check:checked+.btn-outline-info {
                                            background-color: var(--bs-info);
                                            color: white;
                                            border-color: var(--bs-info);
                                        }

                                        .btn-check:checked+.btn-outline-warning {
                                            background-color: var(--bs-warning);
                                            color: white;
                                            border-color: var(--bs-warning);
                                        }

                                        .btn-check:checked+.btn-outline-secondary {
                                            background-color: #5a8dee;
                                            color: white;
                                            border-color: #5a8dee;
                                            box-shadow: 0 2px 4px rgba(90, 141, 238, 0.4);
                                        }

                                        .target-item label {
                                            transition: all 0.2s ease;
                                            font-size: 0.85rem;
                                            background-color: white;
                                        }

                                        .target-item label:hover {
                                            transform: translateY(-2px);
                                        }
                                    </style>
                                </div>

                                <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-label-secondary"
                                        wire:click="resetForm">إلغاء</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti tabler-device-floppy me-1"></i>
                                        {{ $editingFeeId ? 'تحديث البيانات' : 'حفظ المصروف' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card">
                {{-- Search & Filter Bar --}}
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1" style="min-width: 200px; max-width: 350px;">
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="search" class="form-control"
                                       placeholder="بحث باسم المصروف...">
                            </div>
                        </div>
                        <select wire:model.live="filterGender" class="form-select form-select-sm w-auto">
                            <option value="">كل الأنواع</option>
                            <option value="both">الكل</option>
                            <option value="male">ذكور</option>
                            <option value="female">إناث</option>
                        </select>
                        <select wire:model.live="filterSemester" class="form-select form-select-sm w-auto">
                            <option value="">كل الترمات</option>
                            @foreach($semesters as $sem)
                                <option value="{{ $sem->value }}">{{ $sem->label() }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="perPage" class="form-select form-select-sm w-auto">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        @if($search || $filterGender || $filterSemester)
                            <button wire:click="resetSearchFilters" class="btn btn-sm btn-label-secondary text-nowrap">
                                <i class="ti tabler-refresh me-1"></i> إعادة تعيين
                            </button>
                        @endif
                    </div>
                </div>
                <div class="card-datatable table-responsive">
                    <table class="table table-hover border-top">
                        <thead>
                            <tr>
                                <th width="30%" wire:click="sortBy('name')" style="cursor: pointer;">
                                    المصروف
                                    @if($sortField === 'name')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th wire:click="sortBy('amount')" style="cursor: pointer;">
                                    المبلغ
                                    @if($sortField === 'amount')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th>الجنس</th>
                                <th>تكرار الدفع</th>
                                <th>السنة/الترم</th>
                                <th>التخصيص</th>
                                <th width="150">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($additionalFees as $fee)
                                                    <tr class="bg-light fw-bold">
                                                        <td>
                                                            <i class="ti tabler-folder me-2 text-primary"></i>
                                                            {{ $fee->name }}
                                                        </td>
                                                        <td>{{ number_format($fee->amount, 2) }} LE</td>
                                                        <td>
                                                            @if($fee->gender == 'both')
                                                                <span class="badge bg-label-info">الكل</span>
                                                            @elseif($fee->gender == 'male')
                                                                <span class="badge bg-label-primary">ذكور</span>
                                                            @else
                                                                <span class="badge bg-label-danger">إناث</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-switch d-inline-block">
                                                                <input class="form-check-input cursor-pointer" type="checkbox"
                                                                       wire:click="toggleBoolean({{ $fee->id }}, 'is_one_time')"
                                                                       wire:loading.attr="disabled"
                                                                       wire:target="toggleBoolean({{ $fee->id }}, 'is_one_time')"
                                                                       {{ $fee->is_one_time ? 'checked' : '' }}>
                                                                <label class="form-check-label small text-muted">
                                                                    {{ $fee->is_one_time ? 'مرة واحدة' : 'متكرر' }}
                                                                </label>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            @if($fee->year)
                                                                <span class="badge bg-label-primary">{{ $fee->year->year }}</span>
                                                            @endif
                                                            @if($fee->semester)
                                                                <span class="badge bg-label-info">{{ $fee->semester->label() }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                أقسام: {{ $fee->departments->count() }} |
                                                                فرق: {{ $fee->levels->count() }} |
                                                                شعب: {{ $fee->sections->count() }}
                                                            </small>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <button class="btn btn-sm btn-icon edit-record"
                                                                    wire:click="edit({{ $fee->id }})">
                                                                    <i class="ti tabler-edit"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-icon delete-record text-danger"
                                                                    onclick="confirmAction('حذف المصروف', 'هل أنت متأكد من الحذف؟', () => @this.call('delete', {{ $fee->id }}))">
                                                                    <i class="ti tabler-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @foreach($fee->items as $item)
                                                                        <tr>
                                                                            <td class="ps-5">
                                                                                <i class="ti tabler-corner-down-left me-2 text-muted"></i>
                                                                                {{ $item->name }}
                                                                            </td>
                                                                            <td>{{ number_format($item->amount, 2) }} LE</td>
                                                                            <td colspan="5"></td>
                                                                        </tr>
                                                    @endforeach
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">لا يوجد مصاريف مضافة حالياً.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($additionalFees->hasPages())
                    <div class="card-footer border-top d-flex justify-content-center">
                        {{ $additionalFees->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
