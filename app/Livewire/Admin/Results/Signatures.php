<?php

namespace App\Livewire\Admin\Results;

use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Names and signature images printed at the bottom of the grade sheet.
 */
class Signatures extends Component
{
    use WithFileUploads;

    /** @var array<string, string|null> */
    public array $names = [];

    /** @var array<string, mixed> */
    public array $images = [];

    public function mount(): void
    {
        $settings = Setting::query()->first();

        foreach (array_keys(Setting::SIGNATURE_ROLES) as $role) {
            $this->names[$role] = $settings?->{"signature_{$role}_name"};
        }
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('results.signatures'), 403);

        $this->validate([
            'names.*' => 'nullable|string|max:255',
            'images.*' => 'nullable|image|max:1024',
        ], [
            'names.*.max' => 'الاسم لا يزيد عن 255 حرف.',
            'images.*.image' => 'التوقيع لازم يكون صورة.',
            'images.*.max' => 'حجم الصورة أكبر من 1 ميجا.',
        ]);

        $settings = Setting::query()->firstOrCreate([]);
        $values = [];

        foreach (array_keys(Setting::SIGNATURE_ROLES) as $role) {
            $values["signature_{$role}_name"] = filled($this->names[$role] ?? null) ? trim($this->names[$role]) : null;

            if (! empty($this->images[$role])) {
                $this->deleteImage($settings->{"signature_{$role}_image"});
                $values["signature_{$role}_image"] = $this->images[$role]->store('signatures', 'public');
            }
        }

        $settings->update($values);
        $this->images = [];

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ التوقيعات.']);
    }

    public function removeImage(string $role): void
    {
        abort_unless(auth()->user()->can('results.signatures'), 403);
        abort_unless(array_key_exists($role, Setting::SIGNATURE_ROLES), 404);

        $settings = Setting::query()->firstOrCreate([]);
        $this->deleteImage($settings->{"signature_{$role}_image"});
        $settings->update(["signature_{$role}_image" => null]);
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function render(): View
    {
        return view('livewire.admin.results.signatures', [
            'roles' => Setting::SIGNATURE_ROLES,
            'settings' => Setting::query()->first(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
