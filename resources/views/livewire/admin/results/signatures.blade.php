<div>
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">النتائج /</span> إعدادات التوقيعات
    </h4>

    <div class="card">
        <div class="card-body">
            <small class="text-muted d-block mb-4">الأسماء والتوقيعات اللي بتتطبع في آخر كشف الدرجات. الخانة الفاضية بتتطبع فاضية.</small>
            <form wire:submit="save">
                <div class="row g-4">
                    @foreach($roles as $role => $label)
                        <div class="col-md-4" wire:key="signature-{{ $role }}">
                            <div class="border rounded p-3 h-100">
                                <h6 class="fw-bold">{{ $label }}</h6>
                                <label class="form-label" for="name-{{ $role }}">الاسم</label>
                                <input type="text" id="name-{{ $role }}" wire:model="names.{{ $role }}" class="form-control mb-2 @error('names.'.$role) is-invalid @enderror">
                                @error('names.'.$role) <div class="invalid-feedback">{{ $message }}</div> @enderror

                                <label class="form-label" for="image-{{ $role }}">صورة التوقيع</label>
                                <input type="file" id="image-{{ $role }}" wire:model="images.{{ $role }}" accept="image/*" class="form-control @error('images.'.$role) is-invalid @enderror">
                                @error('images.'.$role) <div class="invalid-feedback">{{ $message }}</div> @enderror

                                @if($image = $settings?->{"signature_{$role}_image"})
                                    <div class="mt-2 d-flex align-items-center gap-2">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image) }}" alt="{{ $label }}" style="max-height: 60px;">
                                        <button type="button" class="btn btn-sm btn-label-danger" wire:click="removeImage('{{ $role }}')" wire:confirm="مسح صورة التوقيع؟">مسح</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @can('results.signatures')
                    <button type="submit" class="btn btn-primary mt-4" wire:loading.attr="disabled"><i class="ti tabler-device-floppy me-1"></i> حفظ</button>
                @endcan
            </form>
        </div>
    </div>
</div>
