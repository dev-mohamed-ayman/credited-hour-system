<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> تعديل نتيجة طالب
    </h4>

    <div class="card mb-4">
        <div class="card-body">
            <form wire:submit="search" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="studentCode">كود الطالب</label>
                    <input type="text" id="studentCode" wire:model="studentCode" class="form-control @error('studentCode') is-invalid @enderror">
                    @error('studentCode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="yearId">السنة الدراسية</label>
                    <select id="yearId" wire:model.live="yearId" class="form-select">
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="semester">الترم</label>
                    <select id="semester" wire:model.live="semester" class="form-select">
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="ti tabler-search me-1"></i> بحث</button>
                </div>
            </form>
        </div>
    </div>

    @if($student)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ $student->name }} <span class="text-muted small">({{ $student->username }} — {{ $student->level?->name }})</span></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>المادة</th>
                            <th class="text-center">التحريري</th>
                            <th class="text-center">أعمال السنة</th>
                            <th class="text-center">المجموع</th>
                            <th class="text-center">التقدير</th>
                            <th>ملاحظة</th>
                            <th>آخر رفع</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($enrollments as $enrollment)
                            <tr wire:key="enrollment-{{ $enrollment->id }}" @class(['table-active' => $enrollmentId === $enrollment->id])>
                                <td>{{ $enrollment->course?->code }} — {{ $enrollment->course?->name }}</td>
                                <td class="text-center">{{ $enrollment->written_score ?? $enrollment->exam_status?->label() ?? '—' }}</td>
                                <td class="text-center">{{ $enrollment->coursework_score ?? '—' }}</td>
                                <td class="text-center">{{ $enrollment->total_score ?? $enrollment->exam_status?->label() ?? '—' }}</td>
                                <td class="text-center"><span class="badge bg-label-primary">{{ $enrollment->grade?->name ?? 'Pending' }}</span></td>
                                <td class="small">{{ $enrollment->result_note }}</td>
                                <td class="small text-muted">{{ $enrollment->resultUploadedBy?->name }} {{ $enrollment->result_uploaded_at?->format('Y-m-d') }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-label-primary" wire:click="edit({{ $enrollment->id }})">تعديل</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">لا توجد مواد معتمدة للطالب في الترم ده.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($enrollmentId)
        <div class="card mb-4">
            <h5 class="card-header">تعديل النتيجة</h5>
            <div class="card-body">
                <small class="text-muted d-block mb-3">
                    نفس قواعد شيت الرفع: اكتب التحريري وأعمال السنة والمجموع (أو غائب / تأديب)، والتقدير بيتحسب من المجموع. أو اختار تقدير من غير درجات زي IC أو W.
                    لو إجراءات نهاية الترم اتنفذت قبل كده، التعديل مش بيعيد حساب الإنذارات والترقية.
                </small>
                <form wire:submit="save" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label" for="written">التحريري</label>
                        <input type="text" id="written" wire:model="written" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="coursework">أعمال السنة</label>
                        <input type="text" id="coursework" wire:model="coursework" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="total">المجموع</label>
                        <input type="text" id="total" wire:model="total" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="grade">التقدير</label>
                        <select id="grade" wire:model="grade" class="form-select">
                            <option value="">من المجموع</option>
                            @foreach($grades as $option)
                                <option value="{{ $option->name }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="note">ملاحظة</label>
                        <input type="text" id="note" wire:model="note" class="form-control">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="reason">سبب التعديل <span class="text-danger">*</span></label>
                        <input type="text" id="reason" wire:model="reason" class="form-control" placeholder="مثال: تظلم رقم 15">
                    </div>

                    @if($resultErrors !== [])
                        <div class="col-12">
                            <div class="alert alert-danger mb-0">
                                <ul class="mb-0">
                                    @foreach($resultErrors as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><i class="ti tabler-device-floppy me-1"></i> حفظ التعديل</button>
                        <button type="button" class="btn btn-label-secondary" wire:click="closeForm">إلغاء</button>
                    </div>
                </form>
            </div>

            @include('livewire.admin.results.partials.change-history', ['changes' => $history])
        </div>
    @endif
</div>
