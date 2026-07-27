<?php

namespace App\Livewire\Admin\GradeSettings;

use App\Enums\Semester;
use App\Models\CourseRegistrationSetting;
use App\Models\CrossLevelVisibility;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\ImprovementGradeSetting;
use App\Models\Level;
use App\Models\Setting;
use Livewire\Component;

class Index extends Component
{
    /** @var array<int> */
    public array $failingGradeIds = [];

    /** @var array<int> */
    public array $improvementGradeIds = [];

    /** @var array<int, array<string, int|null>> */
    public array $maxOptionalSettings = [];

    public bool $allowCrossLevelRegistration = false;

    /** @var array<int, array<int, bool>> */
    public array $crossLevelVisibility = [];

    public function mount(): void
    {
        $settings = Setting::query()->first();
        $this->allowCrossLevelRegistration = (bool) ($settings?->allow_cross_level_registration ?? false);
        $this->failingGradeIds = FailingGradeSetting::query()->pluck('grade_id')->map(fn ($id) => (int) $id)->all();
        $this->improvementGradeIds = ImprovementGradeSetting::query()->pluck('grade_id')->map(fn ($id) => (int) $id)->all();

        $levels = Level::query()->orderBy('id')->get();

        foreach ($levels as $level) {
            foreach ([Semester::FIRST, Semester::SECOND] as $semester) {
                $setting = CourseRegistrationSetting::query()
                    ->where('level_id', $level->id)
                    ->where('term_type', $semester->value)
                    ->first();

                $this->maxOptionalSettings[$level->id][$semester->value] = $setting?->max_optional_courses;
            }

            $visibleIds = Level::getVisibleLevelIds($level->id);
            foreach ($levels as $targetLevel) {
                $this->crossLevelVisibility[$level->id][$targetLevel->id] = in_array($targetLevel->id, $visibleIds, true);
            }
        }
    }

    public function saveGradeLists(): void
    {
        abort_unless(auth()->user()->can('course_registration_settings.edit'), 403);

        $overlap = array_intersect($this->failingGradeIds, $this->improvementGradeIds);

        if (! empty($overlap)) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'لا يمكن أن يكون نفس التقييم في قائمة الرسوب وقائمة التحسين معاً.',
            ]);

            return;
        }

        $pendingGradeId = Grade::query()->where('is_pending_default', true)->value('id');

        FailingGradeSetting::query()->delete();
        ImprovementGradeSetting::query()->delete();

        foreach ($this->failingGradeIds as $gradeId) {
            if ($pendingGradeId && (int) $gradeId === (int) $pendingGradeId) {
                continue;
            }

            FailingGradeSetting::create(['grade_id' => $gradeId]);
        }

        foreach ($this->improvementGradeIds as $gradeId) {
            if ($pendingGradeId && (int) $gradeId === (int) $pendingGradeId) {
                continue;
            }

            ImprovementGradeSetting::create(['grade_id' => $gradeId]);
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات التقييمات بنجاح.']);
    }

    public function saveGeneralSettings(): void
    {
        abort_unless(auth()->user()->can('course_registration_settings.edit'), 403);

        $settings = Setting::query()->firstOrCreate([]);

        $settings->update([
            'allow_cross_level_registration' => $this->allowCrossLevelRegistration,
        ]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات التسجيل العامة بنجاح.']);
    }

    public function saveCrossLevelVisibility(): void
    {
        abort_unless(auth()->user()->can('course_registration_settings.edit'), 403);

        CrossLevelVisibility::query()->delete();

        foreach ($this->crossLevelVisibility as $sourceLevelId => $targets) {
            foreach ($targets as $visibleLevelId => $isVisible) {
                if ($isVisible && (int) $sourceLevelId !== (int) $visibleLevelId) {
                    CrossLevelVisibility::create([
                        'source_level_id' => (int) $sourceLevelId,
                        'visible_level_id' => (int) $visibleLevelId,
                    ]);
                }
            }
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ مصفوفة ظهور المواد عبر الفرق بنجاح.']);
    }

    public function saveMaxOptionalSettings(): void
    {
        abort_unless(auth()->user()->can('course_registration_settings.edit'), 403);

        foreach ($this->maxOptionalSettings as $levelId => $terms) {
            foreach ($terms as $termType => $maxOptional) {
                if ($maxOptional === null || $maxOptional === '') {
                    CourseRegistrationSetting::query()
                        ->where('level_id', $levelId)
                        ->where('term_type', $termType)
                        ->delete();

                    continue;
                }

                $this->validate([
                    "maxOptionalSettings.{$levelId}.{$termType}" => 'required|integer|min:0',
                ]);

                CourseRegistrationSetting::updateOrCreate(
                    [
                        'level_id' => $levelId,
                        'term_type' => $termType,
                    ],
                    [
                        'max_optional_courses' => (int) $maxOptional,
                    ]
                );
            }
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ حدود المواد الاختيارية بنجاح.']);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('course_registration_settings.view'), 403);

        $grades = Grade::query()
            ->where('is_pending_default', false)
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $levels = Level::query()->orderBy('id')->get();

        return view('livewire.admin.grade-settings.index', [
            'grades' => $grades,
            'levels' => $levels,
            'semesters' => [Semester::FIRST, Semester::SECOND],
        ])->extends('admin.layouts.app')->section('content');
    }
}
