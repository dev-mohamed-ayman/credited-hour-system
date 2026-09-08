<div class="form-group mb-3">
    <label for="name" class="form-label">اسم المكان</label>
    <input type="text" name="name" id="name" value="{{ old('name', $venue?->name) }}"
           class="form-control @error('name') is-invalid @enderror" placeholder="مثال: مدرج أ">
    @error('name')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="type" class="form-label">نوع المكان</label>
    <select name="type" id="type" class="form-select @error('type') is-invalid @enderror">
        <option value="">اختر النوع</option>
        @foreach(\App\Enums\VenueType::options() as $value => $label)
            <option value="{{ $value }}" {{ old('type', $venue?->type?->value) == $value ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    @error('type')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label for="capacity" class="form-label">السعة الرسمية (اختياري)</label>
    <input type="number" min="1" name="capacity" id="capacity" value="{{ old('capacity', $venue?->capacity) }}"
           class="form-control @error('capacity') is-invalid @enderror" placeholder="اتركها فارغة إن كانت غير معلومة">
    @error('capacity')
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-group mb-3">
    <label class="form-label">حالة التفعيل</label>
    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
               {{ old('is_active', $venue?->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">مفعّل (متاح للحجز في الجلسات الجديدة)</label>
    </div>
</div>

<div class="form-group mb-3">
    <label for="notes" class="form-label">ملاحظات</label>
    <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes', $venue?->notes) }}</textarea>
</div>
