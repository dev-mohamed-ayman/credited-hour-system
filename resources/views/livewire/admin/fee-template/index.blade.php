<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">قوالب المصاريف الإضافية</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">قوالب المصاريف</li>
                </ol>
            </nav>
        </div>
        @if(!$showForm)
            <button class="btn btn-primary" wire:click="create">
                <i class="ti tabler-plus me-1"></i>
                إضافة قالب مصروف
            </button>
        @endif
    </div>

    <div class="row">
        @if($showForm)
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ $editingId ? 'تعديل قالب مصروف' : 'إضافة قالب مصروف جديد' }}</h5>
                        <button class="btn-close" wire:click="resetForm"></button>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="save">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">وصف / اسم المصروف</label>
                                    <input type="text" class="form-control @error('name') is-invalid @enderror"
                                        wire:model="name" placeholder="مثلاً: طباعة كارنيه بدل فاقد، رصيد مكتبة...">
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">المبلغ (ج.م)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">LE</span>
                                        <input type="number" step="0.01" min="0.01"
                                            class="form-control @error('amount') is-invalid @enderror"
                                            wire:model="amount" placeholder="150.00">
                                    </div>
                                    @error('amount') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">الحالة</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" value="1"
                                               id="is_active" wire:model="is_active">
                                        <label class="form-check-label" for="is_active">
                                            {{ $is_active ? 'مفعل' : 'غير مفعل' }}
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-1">يظهر في القوائم عند الإصدار</small>
                                </div>
                                <div class="col-12 mt-4 d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-label-secondary" wire:click="resetForm">إلغاء</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti tabler-device-floppy me-1"></i>
                                        {{ $editingId ? 'تحديث البيانات' : 'حفظ القالب' }}
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
                <div class="card-header border-bottom">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="flex-grow-1" style="min-width: 200px; max-width: 350px;">
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="ti tabler-search"></i></span>
                                <input type="text" wire:model.live.debounce.300ms="search" class="form-control"
                                       placeholder="بحث باسم المصروف...">
                            </div>
                        </div>
                        <select wire:model.live="filterStatus" class="form-select form-select-sm w-auto">
                            <option value="">كل الحالات</option>
                            <option value="active">فعال فقط</option>
                            <option value="inactive">غير فعال فقط</option>
                        </select>
                        <select wire:model.live="perPage" class="form-select form-select-sm w-auto">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                        @if($search || $filterStatus)
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
                                <th width="10%">#</th>
                                <th wire:click="sortBy('name')" style="cursor: pointer;">
                                    اسم / وصف المصروف
                                    @if($sortField === 'name')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th width="20%" wire:click="sortBy('amount')" style="cursor: pointer;">
                                    المبلغ
                                    @if($sortField === 'amount')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th width="15%" wire:click="sortBy('is_active')" style="cursor: pointer;">
                                    الحالة
                                    @if($sortField === 'is_active')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th width="18%" wire:click="sortBy('created_at')" style="cursor: pointer;">
                                    تاريخ الإضافة
                                    @if($sortField === 'created_at')
                                        <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th width="15%">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($feeTemplates as $index => $template)
                                <tr>
                                    <td class="fw-medium text-muted">{{ $feeTemplates->firstItem() + $index }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="ti tabler-template me-2 text-primary"></i>
                                            <span class="fw-medium">{{ $template->name }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-heading">
                                            {{ number_format($template->amount, 2) }} ج.م
                                        </span>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input cursor-pointer" type="checkbox"
                                                   wire:click="toggleActive({{ $template->id }})"
                                                   wire:loading.attr="disabled"
                                                   wire:target="toggleActive({{ $template->id }})"
                                                   {{ $template->is_active ? 'checked' : '' }}>
                                            <label class="form-check-label small">
                                                @if($template->is_active)
                                                    <span class="badge bg-label-success">فعال</span>
                                                @else
                                                    <span class="badge bg-label-secondary">متوقف</span>
                                                @endif
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $template->created_at?->translatedFormat('d M Y') }}
                                        </small>
                                        @if($template->createdByUser)
                                            <small class="text-muted d-block">بواسطة: {{ $template->createdByUser->name }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <button class="btn btn-sm btn-icon edit-record"
                                                title="تعديل"
                                                wire:click="edit({{ $template->id }})">
                                                <i class="ti tabler-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-icon delete-record text-danger"
                                                title="حذف"
                                                onclick="confirmAction('حذف قالب المصروف', 'هل أنت متأكد من حذف «{{ $template->name }}»؟', () => @this.call('delete', {{ $template->id }}))">
                                                <i class="ti tabler-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="ti tabler-template-off fs-3 d-block mb-2 opacity-50"></i>
                                        <p class="mb-0">لا توجد قوالب مصاريف مضافة حالياً.</p>
                                        <button wire:click="create" class="btn btn-sm btn-label-primary mt-3">
                                            <i class="ti tabler-plus me-1"></i> إضافة أول قالب
                                        </button>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($feeTemplates->hasPages())
                    <div class="card-footer border-top d-flex justify-content-center">
                        {{ $feeTemplates->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
