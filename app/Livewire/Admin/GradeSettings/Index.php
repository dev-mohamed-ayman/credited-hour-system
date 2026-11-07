<?php

namespace App\Livewire\Admin\GradeSettings;

use App\Enums\Semester;
use App\Models\CourseRegistrationSetting;
use App\Models\CrossLevelVisibility;
use App\Models\Department;
use App\Models\FailingGradeSetting;
use App\Models\Grade;
use App\Models\ImprovementGradeSetting;
use App\Models\Level;
use App\Models\RegistrationHourLimit;
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

    public int|string $graduationRequiredHours = '';

    public ?int $hourLimitDepartmentId = null;

    /** @var array<int, array<string, int|string|null>> level_id => semester => max hours */
    public array $hourLimits = [];

    public int|string|null $warnedMaxHours = null;

    /** @var array<int, array<int, bool>> */
    public array $crossLevelVisibility = [];

    public function mount(): void
    {
        $settings = Setting::query()->first();
        $this->allowCrossLevelRegistration = (bool) ($settings?->allow_cross_level_registration ?? false);
        $this->graduationRequiredHours = Setting::graduationRequiredHours();
        $this->warnedMaxHours = Setting::warnedMaxHours();
        $this->hourLimitDepartmentId = Department::query()->orderBy('id')->value('id');
        $this->loadHourLimits();
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

        $this->validate(
            ['graduationRequiredHours' => 'required|integer|min:1|max:500'],
            [
                'graduationRequiredHours.required' => 'ساعات التخرج المطلوبة إجبارية.',
                'graduationRequiredHours.integer' => 'ساعات التخرج لازم تكون رقم صحيح.',
                'graduationRequiredHours.min' => 'ساعات التخرج لازم تكون ساعة واحدة على الأقل.',
                'graduationRequiredHours.max' => 'ساعات التخرج لا يمكن أن تزيد عن 500 ساعة.',
            ],
        );

        $settings = Setting::query()->firstOrCreate([]);

        $settings->update([
            'allow_cross_level_registration' => $this->allowCrossLevelRegistration,
            'graduation_required_hours' => (int) $this->graduationRequiredHours,
        ]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ إعدادات التسجيل العامة بنجاح.']);
    }

    public function updatedHourLimitDepartmentId(): void
    {
        $this->resetValidation();
        $this->loadHourLimits();
    }

    public function saveHourLimits(): void
    {
        abort_unless(auth()->user()->can('course_registration_settings.edit'), 403);

        $this->validate([
            'hourLimitDepartmentId' => 'required|exists:departments,id',
            'hourLimits.*.*' => 'nullable|integer|min:1|max:60',
            'warnedMaxHours' => 'nullable|integer|min:1|max:60',
        ], [
            'hourLimitDepartmentId.required' => 'اختر التخصص.',
            'hourLimits.*.*.integer' => 'الحد لازم يكون رقم صحيح.',
            'hourLimits.*.*.min' => 'الحد لازم يكون ساعة واحدة على الأقل.',
            'hourLimits.*.*.max' => 'الحد لا يزيد عن 60 ساعة.',
            'warnedMaxHours.integer' => 'الحد لازم يكون رقم صحيح.',
            'warnedMaxHours.min' => 'الحد لازم يكون ساعة واحدة على الأقل.',
            'warnedMaxHours.max' => 'الحد لا يزيد عن 60 ساعة.',
        ]);

        foreach ($this->hourLimits as $levelId => $terms) {
            foreach ($terms as $semester => $maxHours) {
                $key = ['department_id' => $this->hourLimitDepartmentId, 'level_id' => (int) $levelId, 'semester' => $semester];

                if ($maxHours === null || $maxHours === '') {
                    RegistrationHourLimit::query()->where($key)->delete();

                    continue;
                }

                RegistrationHourLimit::updateOrCreate($key, ['max_hours' => (int) $maxHours]);
            }
        }

        Setting::query()->firstOrCreate([])->update([
            'warned_max_hours' => $this->warnedMaxHours === '' || $this->warnedMaxHours === null ? null : (int) $this->warnedMaxHours,
        ]);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'تم حفظ حدود الساعات بنجاح.']);
    }

    protected function loadHourLimits(): void
    {
        $limits = $this->hourLimitDepartmentId
            ? RegistrationHourLimit::query()->where('department_id', $this->hourLimitDepartmentId)->get()
            : collect();

        $this->hourLimits = [];

        foreach (Level::query()->orderBy('id')->pluck('id') as $levelId) {
            foreach ([Semester::FIRST, Semester::SECOND] as $semester) {
                $this->hourLimits[$levelId][$semester->value] = $limits
                    ->first(fn (RegistrationHourLimit $limit) => (int) $limit->level_id === (int) $levelId && $limit->semester === $semester)
                    ?->max_hours;
            }
        }
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
            'departments' => Department::query()->orderBy('id')->get(),
            'semesters' => [Semester::FIRST, Semester::SECOND],
        ])->extends('admin.layouts.app')->section('content');
    }
}
