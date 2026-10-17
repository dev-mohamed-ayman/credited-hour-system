<div>
    <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between mb-4 gap-3">
        <div>
            <h4 class="mb-0 fw-bold text-heading">رفع النتيجة</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1 mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">الرئيسية</a></li>
                    <li class="breadcrumb-item active">رفع النتيجة</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="yearId" class="form-label fw-bold">السنة الدراسية</label>
                    <select wire:model.live="yearId" id="yearId" class="form-select @error('yearId') is-invalid @enderror">
                        <option value="">اختر السنة</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                    @error('yearId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label for="semester" class="form-label fw-bold">الترم</label>
                    <select wire:model.live="semester" id="semester" class="form-select @error('semester') is-invalid @enderror">
                        <option value="">اختر الترم</option>
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('semester')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="departmentId" class="form-label fw-bold">التخصص</label>
                    <select wire:model.live="departmentId" id="departmentId" class="form-select">
                        <option value="">كل التخصصات</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="courseId" class="form-label fw-bold">المادة</label>
                    <select wire:model.live="courseId" id="courseId" class="form-select @error('courseId') is-invalid @enderror">
                        <option value="">اختر المادة</option>
                        @foreach($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                        @endforeach
                    </select>
                    @error('courseId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-8">
                    <label for="sheet" class="form-label fw-bold">ملف النتيجة (xlsx أو csv)</label>
                    <input type="file" wire:model="sheet" id="sheet" accept=".xlsx,.csv"
                           class="form-control @error('sheet') is-invalid @enderror">
                    @error('sheet')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="sheet" class="small text-muted mt-1">جاري رفع الملف...</div>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-primary flex-grow-1" wire:click="analyze"
                            wire:loading.attr="disabled" wire:target="analyze,sheet">
                        <span wire:loading.remove wire:target="analyze"><i class="ti tabler-file-search me-1"></i> فحص الملف</span>
                        <span wire:loading wire:target="analyze"><span class="spinner-border spinner-border-sm me-1"></span> جاري الفحص...</span>
                    </button>
                    <button type="button" class="btn btn-label-success" wire:click="downloadTemplate"
                            wire:loading.attr="disabled" wire:target="downloadTemplate" title="تحميل شيت فارغ بطلاب المادة وأرقام جلوسهم">
                        <i class="ti tabler-file-download me-1"></i> نموذج الشيت
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary small">
        <div class="fw-bold mb-1"><i class="ti tabler-info-circle me-1"></i> شكل الملف المطلوب</div>
        <div>الأعمدة بالترتيب: كود الطالب، رقم الجلوس، التحريري، اعمال السنة، المجموع، التقدير، ملاحظة.</div>
        <div>التحريري من {{ \App\Services\ResultUploadService::WRITTEN_MAX }} وأعمال السنة من {{ \App\Services\ResultUploadService::COURSEWORK_MAX }}، والمجموع = التحريري + أعمال السنة، والتقدير يُحسب من المجموع ويُراجع على المكتوب (لو تُرك فارغاً يُحسب تلقائياً).</div>
        <div>يمكن كتابة "غائب" أو "تأديب" في التحريري والمجموع، أو "الغاء" في المجموع، ويكون التقدير F. بدون درجات يُقبل تقدير مثل IC أو W أو FX.</div>
        <div>لو في الملف أي سطر به خطأ لا يتم حفظ أي نتيجة حتى يتم تصحيحه.</div>
    </div>

    @if($savedCount)
        <div class="alert alert-success d-flex align-items-center">
            <i class="ti tabler-circle-check me-2"></i>
            <span>تم رفع نتيجة {{ $savedCount }} طالب في مادة {{ $selectedCourse?->code }} — {{ $selectedCourse?->name }} بنجاح.</span>
        </div>
    @endif

    @if($analysis)
        @if($analysis['errors'])
            <div class="card mb-4 border border-danger">
                <div class="card-header d-flex align-items-center justify-content-between border-bottom">
                    <h5 class="card-title mb-0 text-danger"><i class="ti tabler-alert-triangle me-1"></i> يوجد أخطاء في الملف ولن يتم الحفظ</h5>
                    <span class="badge bg-label-danger fs-6">{{ count($analysis['errors']) }} خطأ</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">السطر</th>
                                <th>الخطأ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($analysis['errors'] as $error)
                                <tr wire:key="error-{{ $loop->index }}">
                                    <td>{{ $error['row'] }}</td>
                                    <td class="text-danger">{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif($analysis['rows'])
            <div class="alert alert-success d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <i class="ti tabler-circle-check me-1"></i>
                    الملف سليم: {{ count($analysis['rows']) }} طالب جاهزين للحفظ.
                    @if($analysis['overwritten'])
                        <span class="fw-bold text-warning">سيتم تعديل نتيجة {{ $analysis['overwritten'] }} طالب لهم نتيجة سابقة.</span>
                    @endif
                </div>
                <button type="button" class="btn btn-success" wire:click="confirm"
                        wire:confirm="تأكيد حفظ نتيجة {{ count($analysis['rows']) }} طالب؟"
                        wire:loading.attr="disabled" wire:target="confirm">
                    <span wire:loading.remove wire:target="confirm"><i class="ti tabler-device-floppy me-1"></i> اعتماد وحفظ النتيجة</span>
                    <span wire:loading wire:target="confirm"><span class="spinner-border spinner-border-sm me-1"></span> جاري الحفظ...</span>
                </button>
            </div>
        @else
            <div class="alert alert-warning">الملف لا يحتوي على أي سطور نتائج.</div>
        @endif

        @if($analysis['missing'])
            <div class="card mb-4 border border-warning">
                <div class="card-header d-flex align-items-center justify-content-between border-bottom">
                    <h5 class="card-title mb-0 text-warning">طلاب مسجلون في المادة وليس لهم نتيجة في الملف</h5>
                    <span class="badge bg-label-warning fs-6">{{ count($analysis['missing']) }} طالب</span>
                </div>
                <div class="card-body pt-3">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($analysis['missing'] as $student)
                            <span class="badge bg-label-secondary" wire:key="missing-{{ $student['code'] }}">{{ $student['code'] }} — {{ $student['name'] }}</span>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-2">نتيجة هؤلاء الطلاب لن تتغير.</div>
                </div>
            </div>
        @endif

        @if($analysis['rows'] && ! $analysis['errors'])
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="card-title mb-0">معاينة النتيجة — {{ $selectedCourse?->code }} — {{ $selectedCourse?->name }}</h5>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>السطر</th>
                                <th>الكود</th>
                                <th>الطالب</th>
                                <th class="text-center">التحريري</th>
                                <th class="text-center">أعمال السنة</th>
                                <th class="text-center">المجموع</th>
                                <th class="text-center">التقدير</th>
                                <th class="text-center">النتيجة السابقة</th>
                                <th>ملاحظة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($analysis['rows'] as $row)
                                @php($examStatus = $row['exam_status'] ? \App\Enums\ExamResultStatus::from($row['exam_status']) : null)
                                <tr wire:key="row-{{ $row['registration_course_id'] }}">
                                    <td>{{ $row['row'] }}</td>
                                    <td>{{ $row['code'] }}</td>
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-center">{{ $row['written_score'] ?? ($examStatus && $examStatus !== \App\Enums\ExamResultStatus::CANCELLED ? $examStatus->label() : '—') }}</td>
                                    <td class="text-center">{{ $row['coursework_score'] ?? '—' }}</td>
                                    <td class="text-center">{{ $row['total_score'] ?? $examStatus?->label() ?? '—' }}</td>
                                    <td class="text-center"><span class="badge bg-label-primary">{{ $row['grade_name'] }}</span></td>
                                    <td class="text-center">
                                        @if($row['previous_grade'])
                                            <span class="badge bg-label-warning">{{ $row['previous_grade'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $row['note'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
