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
            <h5 class="mb-0">إعدادات التسجيل العامة</h5>
        </div>
        <div class="card-body">
            <div class="form-check form-switch custom-switch-primary">
                <input class="form-check-input" type="checkbox" wire:model="allowCrossLevelRegistration"
                       id="allowCrossLevelRegistration">
                <label class="form-check-label fw-medium" for="allowCrossLevelRegistration">
                    السماح بتسجيل مواد من فرق دراسية أخرى
                </label>
            </div>
            <small class="text-muted mt-2 d-block">
                <i class="ti tabler-info-circle ti-xs me-1"></i>
                عند التفعيل: يمكن للطالب رؤية مواد من فرق أخرى بشرط أن تكون مرتبطة بنفس الشعبة.
                عند الإيقاف: تظهر مواد فرقته الدراسية فقط.
            </small>
        </div>
        @can('course_registration_settings.edit')
            <div class="card-footer d-flex justify-content-end border-top pt-3">
                <button type="button" class="btn btn-primary" wire:click="saveGeneralSettings" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveGeneralSettings">
                        <i class="ti tabler-device-floppy me-1"></i> حفظ الإعدادات العامة
                    </span>
                    <span wire:loading wire:target="saveGeneralSettings">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        جاري الحفظ...
                    </span>
                </button>
            </div>
        @endcan
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
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle ti-xs me-1"></i>التقييمات التي تستوجب إعادة تسجيل المادة</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">تقييمات التحسين</label>
                    <select wire:model="improvementGradeIds" class="form-select" multiple size="8">
                        @foreach($grades as $grade)
                            <option value="{{ $grade->id }}">{{ $grade->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted mt-1 d-block"><i class="ti tabler-info-circle ti-xs me-1"></i>التقييمات التي تستوجب تحسين المادة</small>
                </div>
            </div>
        </div>
        @can('course_registration_settings.edit')
            <div class="card-footer d-flex justify-content-end border-top pt-3">
                <button type="button" class="btn btn-primary" wire:click="saveGradeLists" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveGradeLists">
                        <i class="ti tabler-device-floppy me-1"></i> حفظ قوائم التقييمات
                    </span>
                    <span wire:loading wire:target="saveGradeLists">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        جاري الحفظ...
                    </span>
                </button>
            </div>
        @endcan
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
        </div>
        @can('course_registration_settings.edit')
            <div class="card-footer d-flex justify-content-end border-top pt-3">
                <button type="button" class="btn btn-primary" wire:click="saveMaxOptionalSettings" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveMaxOptionalSettings">
                        <i class="ti tabler-device-floppy me-1"></i> حفظ حدود الاختياري
                    </span>
                    <span wire:loading wire:target="saveMaxOptionalSettings">
                        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                        جاري الحفظ...
                    </span>
                </button>
            </div>
        @endcan
    </div>
</div>
