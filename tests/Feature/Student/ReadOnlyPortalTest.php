<?php

use App\Enums\Student\StudentStatus;
use App\Http\Middleware\EnsureStudentAccountIsActive;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

dataset('read-only statuses', [
    'dismissed' => [StudentStatus::DISMISSED],
    'graduated' => [StudentStatus::GRADUATED],
]);

dataset('action routes', [
    'course registration' => ['student.course-registrations.index'],
    'registration records' => ['student.registration-records.index'],
    'change password' => ['student.change-password'],
    'exam schedule' => ['student.exam-schedule'],
    'print seat number' => ['student.print-seat-number'],
]);

it('redirects read-only students away from every action page', function (StudentStatus $status, string $route) {
    $student = Student::factory()->create(['status' => $status]);

    $this->actingAs($student, 'student')
        ->get(route($route))
        ->assertRedirect(route('student.dashboard'))
        ->assertSessionHas('error');
})->with('read-only statuses')->with('action routes');

it('lets read-only students view the dashboard and status statement', function (StudentStatus $status) {
    $student = Student::factory()->create(['status' => $status]);

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertSuccessful()
        ->assertSee('الحساب للعرض فقط')
        ->assertDontSee(route('student.course-registrations.index'))
        ->assertDontSee(route('student.change-password'));

    $this->actingAs($student, 'student')
        ->get(route('student.status-statement'))
        ->assertSuccessful();
})->with('read-only statuses');

it('blocks Livewire actions (non-GET) from read-only students with 403', function () {
    $student = Student::factory()->create(['status' => StudentStatus::DISMISSED]);
    $request = Request::create('/livewire/update', 'POST');
    $request->setUserResolver(fn () => $student);

    expect(fn () => (new EnsureStudentAccountIsActive)->handle($request, fn () => response('ok')))
        ->toThrow(HttpException::class);
});

it('keeps full access for registered students', function () {
    $student = Student::factory()->create(['status' => StudentStatus::REGISTERED]);

    $this->actingAs($student, 'student')
        ->get(route('student.change-password'))
        ->assertSuccessful();

    $this->actingAs($student, 'student')
        ->get(route('student.dashboard'))
        ->assertDontSee('الحساب للعرض فقط');
});
