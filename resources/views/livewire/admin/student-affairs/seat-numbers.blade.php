<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">شؤون الطلاب /</span> أرقام الجلوس
    </h4>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <h5 class="card-header">إنشاء وتوزيع أرقام الجلوس</h5>
                <div class="card-body">
                    
                    @if($this->lastSeatNumber)
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <span class="alert-icon text-info me-2">
                                <i class="ti ti-info-circle ti-xs"></i>
                            </span>
                            <div>
                                تنبيه: آخر رقم جلوس تم إنشاؤه في النظام هو <strong>{{ $this->lastSeatNumber }}</strong>. يرجى اختيار رقم بداية غير متعارض.
                            </div>
                        </div>
                    @endif

                    <form wire:submit.prevent="generate">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="department_id">التخصص</label>
                                <select id="department_id" class="form-select @error('department_id') is-invalid @enderror" wire:model.live="department_id">
                                    <option value="">-- اختر التخصص --</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="section_id">الشعبة</label>
                                <select id="section_id" class="form-select @error('section_id') is-invalid @enderror" wire:model="section_id">
                                    <option value="">-- اختر الشعبة --</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}">{{ $section->name }}</option>
                                    @endforeach
                                </select>
                                @error('section_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="level_id">الفرقة / المستوى</label>
                                <select id="level_id" class="form-select @error('level_id') is-invalid @enderror" wire:model="level_id">
                                    <option value="">-- اختر الفرقة --</option>
                                    @foreach($levels as $level)
                                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                                    @endforeach
                                </select>
                                @error('level_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="study_status">حالة الطالب (اختياري)</label>
                                <select id="study_status" class="form-select @error('study_status') is-invalid @enderror" wire:model="study_status">
                                    <option value="">-- الكل --</option>
                                    @foreach(\App\Enums\Student\StudyStatus::cases() as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                @error('study_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-4 mt-4">
                                <label class="form-label" for="start_seat_number">بداية رقم الجلوس</label>
                                <input type="number" id="start_seat_number" class="form-control @error('start_seat_number') is-invalid @enderror" wire:model="start_seat_number" placeholder="مثال: 100001">
                                @error('start_seat_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" wire:confirm="سيتم إنشاء أو تحديث أرقام الجلوس للطلاب المطابقين للشروط ترتيباً أبجدياً. هل أنت متأكد؟">
                                <i class="ti ti-settings me-1"></i> إنشاء أرقام الجلوس
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
