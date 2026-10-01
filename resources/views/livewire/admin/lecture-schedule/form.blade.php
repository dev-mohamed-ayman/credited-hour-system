<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">
                {{ $schedule ? 'تعديل جلسة محاضرة' : 'إضافة جلسة محاضرة' }}
            </h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('lecture-schedules.index') }}">جدول المحاضرات</a></li>
                    <li class="breadcrumb-item active">{{ $course->name }}</li>
                </ol>
            </nav>
        </div>
        <span class="badge bg-label-primary fs-6">{{ $course->name }} — {{ $course->department->name }} — {{ $course->level->name }} — الترم {{ $course->semester }}</span>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label" for="venue_id">المكان</label>
                    <select id="venue_id" wire:model.live="venue_id" class="form-select @error('venue_id') is-invalid @enderror">
                        <option value="">اختر المكان</option>
                        @foreach($venues as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} ({{ $v->type->label() }}{{ $v->capacity ? ' — ' . $v->capacity . ' طالب' : '' }})</option>
                        @endforeach
                    </select>
                    @error('venue_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="day">اليوم</label>
                    <select id="day" wire:model="day" class="form-select @error('day') is-invalid @enderror">
                        <option value="">اختر اليوم</option>
                        @foreach(\App\Enums\DayOfWeek::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('day') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
            </div>

            @if(empty($sections))
                <div class="alert alert-warning">
                    لم يتم توزيع طلاب {{ $course->department->name }} — {{ $course->level->name }} على السكاشن بعد.
                    @can('registration_fees.edit')
                        <a href="{{ route('registration-fees.index') }}" class="alert-link">توزيع الطلاب من الإعدادات</a>
                    @endcan
                </div>
            @endif

            @if(!empty($sections))
                <div class="alert alert-info py-2 d-flex flex-wrap align-items-center gap-2">
                    <span class="fw-bold me-1">تحديد سريع:</span>
                    <select wire:model="range_from" class="form-select form-select-sm w-auto">
                        <option value="">من سكشن</option>
                        @foreach($sections as $number => $count)
                            <option value="{{ $number }}" wire:key="range-from-{{ $number }}">{{ $number }}</option>
                        @endforeach
                    </select>
                    <select wire:model="range_to" class="form-select form-select-sm w-auto">
                        <option value="">إلى سكشن</option>
                        @foreach($sections as $number => $count)
                            <option value="{{ $number }}" wire:key="range-to-{{ $number }}">{{ $number }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" wire:click="applyRange">تحديد المدى</button>
                    <button type="button" class="btn btn-sm btn-label-primary" wire:click="selectAllSections">تحديد الكل</button>
                    <button type="button" class="btn btn-sm btn-label-secondary" wire:click="clearSections">إلغاء التحديد</button>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-md-7">
                    <label class="form-label fw-bold">
                        السكاشن الحاضرة ({{ count($sections) }} سكشن متاح — المختار {{ count($section_numbers) }})
                    </label>
                    <div class="border rounded p-3" style="max-height: 320px; overflow-y: auto;">
                        <div class="row g-2">
                            @forelse($sections as $number => $count)
                                <div class="col-6 col-md-4" wire:key="section-number-{{ $number }}">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" wire:model.live="section_numbers"
                                               value="{{ $number }}" id="section-number-{{ $number }}">
                                        <label class="form-check-label" for="section-number-{{ $number }}">
                                            سكشن {{ $number }}
                                            <span class="badge bg-label-secondary ms-1">{{ $count }} طالب</span>
                                        </label>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12 text-muted">لا توجد سكاشن متاحة بعد.</div>
                            @endforelse
                        </div>
                    </div>
                    @error('section_numbers') <div class="text-danger mt-1 small">{{ $message }}</div> @enderror
                    @error('section_numbers.*') <div class="text-danger mt-1 small">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label fw-bold">السعة</label>
                    @if($venue && $venue->capacity === null)
                        <div class="alert alert-warning py-2 mb-0">سعة غير محددة لهذا المكان — سيتم تخطي فحص السعة.</div>
                    @elseif($venue)
                        @php
                            $percent = $venue->capacity > 0 ? min(100, round($selectedTotal / $venue->capacity * 100)) : 0;
                            $over = $selectedTotal > $venue->capacity;
                        @endphp
                        <div class="d-flex justify-content-between small mb-1">
                            <span>الإجمالي المختار: <strong>{{ $selectedTotal }}</strong> طالب</span>
                            <span>السعة: <strong>{{ $venue->capacity }}</strong></span>
                        </div>
                        <div class="progress" role="progressbar" style="height: 10px;">
                            <div class="progress-bar {{ $over ? 'bg-danger' : 'bg-success' }}" style="width: {{ $percent }}%"></div>
                        </div>
                        @if($over)
                            <div class="text-danger small mt-1">تحذير: تجاوز سعة المكان — لن يتم الحفظ.</div>
                        @endif
                    @else
                        <div class="text-muted">اختر مكانًا لعرض السعة.</div>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <button type="button" class="btn btn-primary" wire:click="save">
                    <span wire:loading.remove wire:target="save">حفظ</span>
                    <span wire:loading wire:target="save" class="spinner-border spinner-border-sm"></span>
                </button>
                <a href="{{ route('lecture-schedules.index', ['course' => $course->id]) }}" class="btn btn-label-secondary">إلغاء</a>
            </div>
        </div>
    </div>
</div>
