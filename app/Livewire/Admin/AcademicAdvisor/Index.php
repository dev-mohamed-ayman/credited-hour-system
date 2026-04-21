<?php

namespace App\Livewire\Admin\AcademicAdvisor;

use App\Models\AcademicAdvisor;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public $search = '';

    #[Url(except: 10)]
    public $perPage = 10;

    public $transferAdvisorId = null;

    public $transferMode = 'auto'; // 'auto' or 'specific'

    public $targetAdvisorId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        AcademicAdvisor::findOrFail($id)->delete();
        $this->dispatch('toast', ['message' => 'تم حذف المرشد الأكاديمي بنجاح', 'type' => 'success']);
    }

    public function toggleStatus($id): void
    {
        $advisor = AcademicAdvisor::findOrFail($id);
        $advisor->update(['is_active' => ! $advisor->is_active]);
        $this->dispatch('toast', ['message' => 'تم تغيير حالة المرشد الأكاديمي بنجاح', 'type' => 'success']);
    }

    public function openTransferModal($id): void
    {
        $this->transferAdvisorId = $id;
        $this->transferMode = 'auto';
        $this->targetAdvisorId = null;
        $this->resetErrorBag();
        $this->dispatch('open-transfer-modal');
    }

    #[Computed]
    public function activeAdvisors()
    {
        if (! $this->transferAdvisorId) {
            return collect();
        }

        return AcademicAdvisor::where('is_active', true)
            ->where('id', '!=', $this->transferAdvisorId)
            ->get();
    }

    public function transferStudents(): void
    {
        $advisor = AcademicAdvisor::with('students.section')->findOrFail($this->transferAdvisorId);
        $students = $advisor->students;

        if ($students->isEmpty()) {
            $advisor->update(['is_active' => false]);
            $this->dispatch('close-transfer-modal');
            $this->dispatch('toast', ['message' => 'تم إيقاف المرشد بنجاح (لا يوجد طلاب لتحويلهم)', 'type' => 'success']);

            return;
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($advisor, $students) {
                if ($this->transferMode === 'specific') {
                    $this->validate([
                        'targetAdvisorId' => 'required|exists:academic_advisors,id',
                    ], [
                        'targetAdvisorId.required' => 'يرجى اختيار المرشد الأكاديمي البديل.',
                        'targetAdvisorId.exists' => 'المرشد الأكاديمي المختار غير صالح.',
                    ]);

                    $targetAdvisor = AcademicAdvisor::findOrFail($this->targetAdvisorId);

                    if ($targetAdvisor->max_students - $targetAdvisor->current_students < $students->count()) {
                        throw new \Exception('المرشد المحدد لا يملك سعة كافية لاستيعاب هؤلاء الطلاب. متاح: '.($targetAdvisor->max_students - $targetAdvisor->current_students));
                    }

                    foreach ($students as $student) {
                        $student->update(['academic_advisor_id' => $targetAdvisor->id]);
                    }

                    $targetAdvisor->update([
                        'current_students' => $targetAdvisor->current_students + $students->count(),
                    ]);

                } else {
                    // Auto transfer
                    $untransferredCount = 0;

                    foreach ($students as $student) {
                        $departmentId = $student->section->department_id ?? null;

                        $targetAdvisor = AcademicAdvisor::query()
                            ->where('is_active', true)
                            ->where('id', '!=', $advisor->id)
                            ->whereHas('assignments', function ($query) use ($departmentId, $student) {
                                $query->where(function ($q) use ($departmentId) {
                                    $q->where('department_id', $departmentId)
                                        ->orWhereNull('department_id');
                                })->where(function ($q) use ($student) {
                                    $q->where('section_id', $student->section_id)
                                        ->orWhereNull('section_id');
                                })->where(function ($q) use ($student) {
                                    $q->where('level_id', $student->level_id)
                                        ->orWhereNull('level_id');
                                });
                            })
                            ->whereColumn('current_students', '<', 'max_students')
                            ->orderBy('current_students')
                            ->first();

                        if ($targetAdvisor) {
                            $student->update(['academic_advisor_id' => $targetAdvisor->id]);
                            $targetAdvisor->increment('current_students');
                        } else {
                            $untransferredCount++;
                        }
                    }

                    if ($untransferredCount > 0) {
                        throw new \Exception('التوزيع التلقائي فشل. لا يوجد سعة كافية أو مرشدين متاحين لنقل '.$untransferredCount.' طالب.');
                    }
                }

                $advisor->update([
                    'current_students' => 0, // Since all have been transferred successfully
                    'is_active' => false,
                ]);
            });

            $this->dispatch('close-transfer-modal');
            $this->dispatch('toast', ['message' => 'تم تحويل الطلاب وإيقاف المرشد بنجاح', 'type' => 'success']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('toast', ['message' => $e->getMessage(), 'type' => 'error']);
        }
    }

    public function render()
    {
        $advisors = AcademicAdvisor::query()
            ->with(['assignments.department', 'assignments.section', 'assignments.level'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('username', 'like', '%'.$this->search.'%');
            })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.admin.academic-advisor.index', [
            'advisors' => $advisors,
        ]);
    }
}
