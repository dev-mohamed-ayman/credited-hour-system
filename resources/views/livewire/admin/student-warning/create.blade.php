<div>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">إضافة تنبيه للطلاب</h5>
            <a href="{{ route('student-warnings.index') }}" class="btn btn-secondary btn-sm">رجوع</a>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="student_codes">أكواد الطلاب</label>
                        <textarea id="student_codes" wire:model="student_codes" class="form-control" rows="3" placeholder="أدخل أكواد الطلاب مفصولة بمسافة، فاصلة، أو سطر جديد..."></textarea>
                        <small class="text-muted">مثال: E260001, E260002</small>
                        @error('student_codes') <span class="text-danger d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="type">نوع التنبيه</label>
                        <select id="type" wire:model="type" class="form-select">
                            <option value="{{ \App\Enums\Student\StudentWarningType::WARNING->value }}">تحذير (تحذير عادي)</option>
                            <option value="{{ \App\Enums\Student\StudentWarningType::DANGER->value }}">خطر (إيقاف حساب الطالب)</option>
                        </select>
                        @error('type') <span class="text-danger d-block">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="reason">السبب</label>
                        <textarea id="reason" wire:model="reason" class="form-control" rows="3" placeholder="أدخل سبب التنبيه..."></textarea>
                        @error('reason') <span class="text-danger d-block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading wire:target="save" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        حفظ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
