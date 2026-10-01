<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-1">تسجيل المواد الأساسية تلقائياً</h5>
        <small class="text-muted">
            يسجّل المواد الأساسية لفرقة كل طالب في الترم المختار، للطلاب الذين ليس لديهم مواد رسوب أو تحسين أو مواد متأخرة فقط.
            باقي الطلاب يتم تخطيهم ويُسجَّلون يدوياً.
        </small>
    </div>
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-bold">السنة الدراسية</label>
                <select class="form-select" wire:model="yearId">
                    <option value="">اختر السنة</option>
                    @foreach($years as $year)
                        <option value="{{ $year->id }}">{{ $year->year }}</option>
                    @endforeach
                </select>
                @error('yearId')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">الترم</label>
                <select class="form-select" wire:model="semester">
                    <option value="">اختر الترم</option>
                    @foreach($semesters as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                @error('semester')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">التخصص</label>
                <select class="form-select" wire:model="departmentId">
                    <option value="">الكل</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">الفرقة</label>
                <select class="form-select" wire:model="levelId">
                    <option value="">الكل</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-primary w-100" wire:click="register"
                        wire:confirm="سيتم تسجيل المواد الأساسية تلقائياً لكل الطلاب المؤهلين وخصم الرسوم من محافظهم. هل أنت متأكد؟"
                        wire:loading.attr="disabled" wire:target="register">
                    <span wire:loading.remove wire:target="register"><i class="ti tabler-wand me-1"></i> تسجيل تلقائي</span>
                    <span wire:loading wire:target="register">جاري التسجيل...</span>
                </button>
            </div>
        </div>

        @if($result)
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="alert alert-success mb-2">تم تسجيل {{ count($result['registered']) }} طالب</div>
                    @if(count($result['registered']))
                        <div class="table-responsive" style="max-height: 320px">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>الكود</th><th>الاسم</th><th>عدد المواد</th></tr></thead>
                                <tbody>
                                    @foreach($result['registered'] as $row)
                                        <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['courses'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning mb-2">تم تخطي {{ count($result['skipped']) }} طالب</div>
                    @if(count($result['skipped']))
                        <div class="table-responsive" style="max-height: 320px">
                            <table class="table table-sm table-bordered mb-0">
                                <thead><tr><th>الكود</th><th>الاسم</th><th>السبب</th></tr></thead>
                                <tbody>
                                    @foreach($result['skipped'] as $row)
                                        <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td class="small">{{ $row['reason'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
