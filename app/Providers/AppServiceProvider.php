<?php

namespace App\Providers;

use App\Models\MilitaryEducationCourse;
use App\Observers\MilitaryEducationCourseObserver;
use Illuminate\Support\ServiceProvider;

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
    }
}
