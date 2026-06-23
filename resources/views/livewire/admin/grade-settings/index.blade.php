<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">إعدادات تسجيل المواد</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">إعدادات التسجيل</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">قوائم التقييمات المتحكمة في التسجيل</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">تقييمات الرسوب (إعادة التسجيل)</label>
                    <select wire:model="failingGradeIds" class="form-select" multiple size="8">
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">التقييمات التي تستوجب إعادة تسجيل المادة</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">تقييمات التحسين</label>
                    <select wire:model="improvementGradeIds" class="form-select" multiple size="8">
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">التقييمات التي تستوجب تحسين المادة</small>
                </div>
            </div>
            @can('course_registration_settings.edit')
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" wire:click="saveGradeLists" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveGradeLists">حفظ قوائم التقييمات</span>
                        <span wire:loading wire:target="saveGradeLists">جاري الحفظ...</span>
                    </button>
                </div>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">الحد الأقصى للمواد الاختيارية (لكل فرقة × ترم)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>الفرقة الدراسية</th>
                            @foreach($semesters as $semester)
                                <th class="text-center">{{ $semester->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($levels as $level)
                            <tr>
                                <td class="fw-medium">{{ $level->name }}</td>
                                @foreach($semesters as $semester)
                                    <td>
                                        <input type="number" min="0"
                                               wire:model="maxOptionalSettings.{{ $level->id }}.{{ $semester->value }}"
                                               class="form-control form-control-sm text-center"
                                               placeholder="—">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('course_registration_settings.edit')
                <button type="button" class="btn btn-primary" wire:click="saveMaxOptionalSettings" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveMaxOptionalSettings">حفظ حدود الاختياري</span>
                    <span wire:loading wire:target="saveMaxOptionalSettings">جاري الحفظ...</span>
                </button>
            @endcan
        </div>
    </div>
</div>
