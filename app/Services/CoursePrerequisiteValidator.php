<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Collection;

class CoursePrerequisiteValidator
{
    /**
     * @param  array<int>  $prerequisiteIds
     */
    public function validate(int $courseId, array $prerequisiteIds): ?string
    {
        if (in_array($courseId, $prerequisiteIds, true)) {
            return 'لا يمكن للمادة أن تكون متطلبًا سابقًا لنفسها.';
        }

        foreach ($prerequisiteIds as $prerequisiteId) {
            if ($this->createsCircularDependency($courseId, $prerequisiteId)) {
                return 'لا يمكن إضافة هذا المتطلب لأنه يُنشئ تبعية دائرية.';
            }
        }

        return null;
    }

    public function createsCircularDependency(int $courseId, int $prerequisiteId): bool
    {
        $visited = [];
        $queue = [$prerequisiteId];

        while (! empty($queue)) {
            $currentId = array_shift($queue);

            if ($currentId === $courseId) {
                return true;
            }

            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            $course = Course::query()->with('prerequisites:id')->find($currentId);

            if (! $course) {
                continue;
            }

            foreach ($course->prerequisites as $prerequisite) {
                if (! isset($visited[$prerequisite->id])) {
                    $queue[] = $prerequisite->id;
                }
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Course>  $allCourses
     * @param  array<int>  $selectedIds
     * @return Collection<int, Course>
     */
    public function availablePrerequisites(Collection $allCourses, array $selectedIds, ?int $courseId = null): Collection
    {
        return $allCourses->filter(function (Course $candidate) use ($courseId) {
            if ($courseId !== null && $candidate->id === $courseId) {
                return false;
            }

            if ($courseId !== null && $this->createsCircularDependency($courseId, $candidate->id)) {
                return false;
            }

            return true;
        })->values();
    }
}
