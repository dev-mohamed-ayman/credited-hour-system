<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> ملاحظات الطلاب
    </h4>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="yearId">السنة الدراسية</label>
                    <select id="yearId" wire:model.live="yearId" class="form-select @error('yearId') is-invalid @enderror">
                        <option value="">اختر السنة</option>
                        @foreach($years as $year)
                            <option value="{{ $year->id }}">{{ $year->year }}</option>
                        @endforeach
                    </select>
                    @error('yearId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold" for="semester">الترم</label>
                    <select id="semester" wire:model.live="semester" class="form-select @error('semester') is-invalid @enderror">
                        <option value="">اختر الترم</option>
                        @foreach($semesters as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    @error('semester') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold" for="sheet">ملف الملاحظات (xlsx أو csv)</label>
                    <input type="file" id="sheet" wire:model="sheet" accept=".xlsx,.csv" class="form-control @error('sheet') is-invalid @enderror">
                    @error('sheet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
            <small class="text-muted d-block mt-3">
                عمودين: <b>كود الطالب</b> و <b>الملاحظة</b>. الملاحظة بتتطبع في كشف الدرجات في الترم ده، والخانة الفاضية بتمسح ملاحظة الطالب.
                لو في سطر واحد غلط مفيش حاجة بتتحفظ.
            </small>
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-label-primary" wire:click="analyze" wire:loading.attr="disabled">فحص الملف</button>
                @if($analysis && $analysis['errors'] === [] && $analysis['rows'] !== [])
                    <button type="button" class="btn btn-primary" wire:click="confirm" wire:loading.attr="disabled">حفظ {{ count($analysis['rows']) }} ملاحظة</button>
                @endif
            </div>

            @if($analysis && $analysis['errors'] !== [])
                <div class="alert alert-danger mt-3 mb-0">
                    <ul class="mb-0">
                        @foreach($analysis['errors'] as $error)
                            <li>سطر {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @elseif($analysis)
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead class="table-light"><tr><th>الكود</th><th>الاسم</th><th>الملاحظة</th></tr></thead>
                        <tbody>
                            @foreach($analysis['rows'] as $row)
                                <tr><td>{{ $row['code'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['note'] ?? '(مسح الملاحظة)' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">ملاحظات الترم ({{ $notes->count() }})</h5>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light"><tr><th>الكود</th><th>الاسم</th><th>الملاحظة</th><th></th></tr></thead>
                <tbody>
                    @forelse($notes as $note)
                        <tr wire:key="note-{{ $note->id }}">
                            <td>{{ $note->student?->username }}</td>
                            <td>{{ $note->student?->name }}</td>
                            <td>{{ $note->note }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-label-danger" wire:click="delete({{ $note->id }})" wire:confirm="مسح الملاحظة؟">مسح</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">لا توجد ملاحظات في الترم ده.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
