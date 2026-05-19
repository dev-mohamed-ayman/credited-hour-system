<?php

namespace App\Livewire\Admin\AdditionalFee;

use App\Models\AdditionalFee;
use App\Models\Department;
use App\Models\Level;
use App\Models\Section;
use Livewire\Component;

class Index extends Component
{
    public $name;

    public $amount = 0;

    public $gender = 'both';

    public $is_one_time = true;

    public $parent_id = null;

    public $selectedDepartments = [];

    public $selectedLevels = [];

    public $selectedSections = [];

    public $editingFeeId = null;

    public $showForm = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'amount' => 'required|numeric|min:0',
        'gender' => 'required|in:male,female,both',
        'is_one_time' => 'required|boolean',
        'parent_id' => 'nullable|exists:additional_fees,id',
        'selectedDepartments' => 'array',
        'selectedLevels' => 'array',
        'selectedSections' => 'array',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->name = '';
        $this->amount = 0;
        $this->gender = 'both';
        $this->is_one_time = true;
        $this->parent_id = null;

        // Default to all selected
        $this->selectedDepartments = Department::pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->selectedLevels = Level::pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->selectedSections = Section::pluck('id')->map(fn ($id) => (string) $id)->toArray();

        $this->editingFeeId = null;
        $this->showForm = false;
        $this->resetErrorBag();
    }

    public function create()
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit($id)
    {
        $fee = AdditionalFee::with(['departments', 'levels', 'sections'])->findOrFail($id);

        $this->editingFeeId = $fee->id;
        $this->name = $fee->name;
        $this->amount = $fee->amount;
        $this->gender = $fee->gender;
        $this->is_one_time = $fee->is_one_time;
        $this->parent_id = $fee->parent_id;

        $this->selectedDepartments = $fee->departments->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->selectedLevels = $fee->levels->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        $this->selectedSections = $fee->sections->pluck('id')->map(fn ($id) => (string) $id)->toArray();

        $this->showForm = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'amount' => $this->amount,
            'gender' => $this->gender,
            'is_one_time' => $this->is_one_time,
            'parent_id' => $this->parent_id,
        ];

        if ($this->editingFeeId) {
            $fee = AdditionalFee::findOrFail($this->editingFeeId);
            $fee->update($data);
        } else {
            $fee = AdditionalFee::create($data);
        }

        $fee->departments()->sync($this->selectedDepartments);
        $fee->levels()->sync($this->selectedLevels);
        $fee->sections()->sync($this->selectedSections);

        session()->flash('message', $this->editingFeeId ? 'تم تحديث المصروف بنجاح.' : 'تم إضافة المصروف بنجاح.');
        $this->resetForm();
    }

    public function delete($id)
    {
        AdditionalFee::findOrFail($id)->delete();
        session()->flash('message', 'تم حذف المصروف بنجاح.');
    }

    public function selectAllDepartments()
    {
        $allIds = Department::pluck('id')->map(fn ($id) => (string) $id)->toArray();

        if (count($this->selectedDepartments) === count($allIds)) {
            $this->selectedDepartments = [];
        } else {
            $this->selectedDepartments = $allIds;
        }
    }

    public function selectAllLevels()
    {
        $allIds = Level::pluck('id')->map(fn ($id) => (string) $id)->toArray();

        if (count($this->selectedLevels) === count($allIds)) {
            $this->selectedLevels = [];
        } else {
            $this->selectedLevels = $allIds;
        }
    }

    public function selectAllSections()
    {
        $allIds = Section::pluck('id')->map(fn ($id) => (string) $id)->toArray();

        if (count($this->selectedSections) === count($allIds)) {
            $this->selectedSections = [];
        } else {
            $this->selectedSections = $allIds;
        }
    }

    public function render()
    {
        return view('livewire.admin.additional-fee.index', [
            'additionalFees' => AdditionalFee::with(['children', 'departments', 'levels', 'sections'])
                ->whereNull('parent_id')
                ->get(),
            'parentFees' => AdditionalFee::whereNull('parent_id')
                ->where('id', '!=', $this->editingFeeId)
                ->get(),
            'departments' => Department::all(),
            'levels' => Level::all(),
            'sections' => Section::all(),
        ])->extends('admin.layouts.app')->section('content');
    }
}
