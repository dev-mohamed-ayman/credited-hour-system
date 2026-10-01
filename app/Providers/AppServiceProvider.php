<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStudentAccountIsActive;
use App\Models\Grade;
use App\Models\MilitaryEducationCourse;
use App\Observers\GradeObserver;
use App\Observers\MilitaryEducationCourseObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        MilitaryEducationCourse::observe(MilitaryEducationCourseObserver::class);
        Grade::observe(GradeObserver::class);

        Livewire::addPersistentMiddleware([
            EnsureStudentAccountIsActive::class,
        ]);

        Gate::before(function ($user, $ability) {
            return $user->is_super_admin ? true : null;
        });
    }
}
