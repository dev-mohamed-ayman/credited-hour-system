<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">
                {{ $session ? 'تعديل جلسة امتحان' : 'إضافة جلسة امتحان' }}
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('exam-schedules.index') }}">جدول الامتحانات</a></li>
                    <li class="breadcrumb-item active">{{ $course->name }}</li>
                </ol>
            </nav>
        </div>
        <span class="badge bg-label-primary fs-6">
            {{ $course->name }} — {{ $course->department->name }} — {{ $course->level->name }} — الترم {{ $course->semester }}
        </span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="type">نوع الامتحان</label>
                    <select id="type" wire:model="type" class="form-select @error('type') is-invalid @enderror" @if($session) disabled @endif>
                        @foreach($examTypes as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @if($session)
                        <div class="text-muted small mt-1">لا يمكن تغيير نوع الامتحان بعد الإنشاء — احذف الجلسة وأعد إنشاءها.</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="exam_date">التاريخ</label>
                    <input type="date" id="exam_date" wire:model="exam_date" class="form-control @error('exam_date') is-invalid @enderror">
                    @error('exam_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="start_time">من الساعة</label>
                    <input type="time" step="900" id="start_time" wire:model="start_time" class="form-control @error('start_time') is-invalid @enderror">
                    @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="end_time">إلى الساعة</label>
                    <input type="time" step="900" id="end_time" wire:model="end_time" class="form-control @error('end_time') is-invalid @enderror">
                    @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="notes">ملاحظات</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="form-control @error('notes') is-invalid @enderror"></textarea>
                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="alert d-flex flex-wrap align-items-center gap-2 py-2 mt-4 {{ $capacityTotal >= $examineeCount ? 'alert-info' : 'alert-warning' }}">
                <span>عدد الممتحنين (تسجيلات معتمدة): <span class="fw-bold">{{ $examineeCount }}</span></span>
                <span class="mx-2">|</span>
                <span>إجمالي سعة اللجان: <span class="fw-bold">{{ $capacityTotal }}</span></span>
                @if($capacityTotal < $examineeCount)
                    <span class="badge bg-label-danger">السعة لا تكفي</span>
                @endif
            </div>

            <hr class="my-4">

            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0">لجان الامتحان</h6>
                <button type="button" class="btn btn-sm btn-primary" wire:click="addCommittee">
                    <i class="ti tabler-plus me-1"></i> إضافة لجنة
                </button>
            </div>

            @forelse($committees as $index => $committee)
                <div class="row g-3 align-items-end mb-2" wire:key="committee-{{ $index }}">
                    <div class="col-md-4">
                        <label class="form-label">المكان</label>
                        <select wire:model.live="committees.{{ $index }}.venue_id" class="form-select @error("committees.$index.venue_id") is-invalid @enderror">
                            <option value="">اختر المكان</option>
                            @foreach($venues as $v)
                                <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type->label() }})</option>
                            @endforeach
                        </select>
                        @error("committees.$index.venue_id") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">اسم اللجنة</label>
                        <input type="text" wire:model="committees.{{ $index }}.name" class="form-control @error("committees.$index.name") is-invalid @enderror" placeholder="لجنة 1">
                        @error("committees.$index.name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">السعة</label>
                        <input type="number" min="1" wire:model="committees.{{ $index }}.capacity" class="form-control @error("committees.$index.capacity") is-invalid @enderror">
                        @error("committees.$index.capacity") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-label-danger" wire:click="removeCommittee({{ $index }})">
                            <i class="ti tabler-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-muted">لم تُضف لجان بعد — يمكن حفظ الجلسة وإضافة اللجان لاحقًا قبل توليد التوزيع.</p>
            @endforelse

            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-primary" wire:click="save">
                    <i class="ti tabler-device-floppy me-2"></i> حفظ
                </button>
                <a class="btn btn-label-secondary" href="{{ route('exam-schedules.index', ['year' => $year_id]) }}">إلغاء</a>
            </div>
        </div>
    </div>
</div>
