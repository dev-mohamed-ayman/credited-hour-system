<?php

namespace App\Observers;

use App\Models\Grade;

class GradeObserver
{
    public function saving(Grade $grade): void
    {
        if (! $grade->is_pending_default) {
            return;
        }

        Grade::query()
            ->where('is_pending_default', true)
            ->when($grade->exists, fn ($query) => $query->where('id', '!=', $grade->id))
            ->update(['is_pending_default' => false]);
    }
}
