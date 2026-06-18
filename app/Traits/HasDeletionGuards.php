<?php

namespace App\Traits;

trait HasDeletionGuards
{
    public function hasBlockingRelations(): bool
    {
        foreach ($this->blockingRelations as $relation) {
            if ($this->$relation()->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getBlockingRelationsCount(): array
    {
        $counts = [];

        foreach ($this->blockingRelations as $relation) {
            $count = $this->$relation()->count();
            if ($count > 0) {
                $counts[$relation] = $count;
            }
        }

        return $counts;
    }

    public function getBlockingRelationsMessage(): ?string
    {
        $counts = $this->getBlockingRelationsCount();

        if (empty($counts)) {
            return null;
        }

        $messages = [];
        foreach ($counts as $relation => $count) {
            $relationName = $this->getRelationName($relation);
            $messages[] = "$relationName ($count)";
        }

        return 'لا يمكن الحذف لوجود سجلات مرتبطة: ' . implode(', ', $messages);
    }

    protected function getRelationName(string $relation): string
    {
        $names = [
            'sections' => 'شعب',
            'requirements' => 'متطلبات',
            'levels' => 'فرق',
            'cities' => 'مدن',
            'certificateTypes' => 'شهادات',
            'departments' => 'تخصصات',
            'enrollments' => 'تسجيلات',
            'students' => 'طلاب',
            'items' => 'عناصر',
            'scores' => 'درجات',
            'warnings' => 'تنبيهات',
            'feeTickets' => 'إيصالات رسوم',
            'assignments' => 'تعيينات',
        ];

        return $names[$relation] ?? $relation;
    }
}
