<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> سجل تعديلات النتائج
    </h4>

    <div class="card mb-4">
        <div class="card-body row g-3">
            <div class="col-md-2">
                <label class="form-label" for="yearId">السنة</label>
                <select id="yearId" wire:model.live="yearId" class="form-select">
                    <option value="">الكل</option>
                    @foreach($years as $year)
                        <option value="{{ $year->id }}">{{ $year->year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="semester">الترم</label>
                <select id="semester" wire:model.live="semester" class="form-select">
                    <option value="">الكل</option>
                    @foreach($semesters as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="source">الطريقة</label>
                <select id="source" wire:model.live="source" class="form-select">
                    <option value="">الكل</option>
                    @foreach($sources as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="studentCode">كود الطالب</label>
                <input type="text" id="studentCode" wire:model.live.debounce.500ms="studentCode" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="courseCode">كود المادة</label>
                <input type="text" id="courseCode" wire:model.live.debounce.500ms="courseCode" class="form-control">
            </div>
        </div>
    </div>

    <div class="card">
        @include('livewire.admin.results.partials.change-history', ['changes' => $changes, 'showEnrollment' => true])
        <div class="card-footer">{{ $changes->links() }}</div>
    </div>
</div>
