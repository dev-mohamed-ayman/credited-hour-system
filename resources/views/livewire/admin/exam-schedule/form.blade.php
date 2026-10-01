<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">{{ $committee ? 'تعديل لجنة امتحان' : 'إضافة لجنة امتحان' }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('exam-schedules.index') }}">جدول الامتحانات</a></li>
                    <li class="breadcrumb-item active">{{ $committee?->name ?? 'لجنة جديدة' }}</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">السنة الدراسية / الترم</label>
                    <div class="form-control bg-label-secondary">
                        {{ $termYear?->year ?? 'لا توجد سنة حالية' }} — {{ \App\Enums\Semester::tryFrom((string) $semester)?->label() ?? '—' }}
                    </div>
                    @error('year_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    @error('semester') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name">اسم اللجنة</label>
                    <input type="text" id="name" wire:model="name" class="form-control @error('name') is-invalid @enderror" placeholder="لجنة 1">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="venue_id">المكان</label>
                    <select id="venue_id" wire:model.live="venue_id" class="form-select @error('venue_id') is-invalid @enderror">
                        <option value="">اختر المكان</option>
                        @foreach($venues as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type->label() }} — {{ $v->capacity }})</option>
                        @endforeach
                    </select>
                    @error('venue_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="capacity">السعة (أقصى عدد طلاب)</label>
                    <input type="number" min="1" id="capacity" wire:model="capacity" class="form-control @error('capacity') is-invalid @enderror">
                    @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="notes">ملاحظات</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="form-control @error('notes') is-invalid @enderror"></textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">
                    <i class="ti tabler-device-floppy me-2"></i> حفظ
                </button>
                <a class="btn btn-label-secondary" href="{{ $committee ? route('exam-schedules.manage', $committee) : route('exam-schedules.index', ['year' => $year_id, 'semester' => $semester]) }}">إلغاء</a>
            </div>
        </div>
    </div>
</div>
