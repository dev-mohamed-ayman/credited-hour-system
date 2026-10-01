<?php

use App\Enums\ExamSessionStatus;
use App\Livewire\Admin\ExamSchedule\Form;
use App\Livewire\Admin\ExamSchedule\Index;
use App\Livewire\Admin\ExamSchedule\Manage;
use App\Models\ExamCommittee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the index lists the term committees and the unassigned count', function () {
    $world = examWorld();
    $committee = examCommittee($world);
    examService()->addStudentsByCodes($committee, $world['students']->take(2)->pluck('username')->all());

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('year_id', $world['year']->id)
        ->set('semester', 'first')
        ->assertSee('لجنة 1')
        ->assertSee('مدرج أ')
        ->assertSee('2 / 10')
        ->assertSee('3</b> طالب', false)
        ->call('toggleUnassigned')
        ->assertSee($world['students']->last()->username);
});

test('the index can find a committee by a student code', function () {
    $world = examWorld();
    $first = examCommittee($world);
    examService()->addStudentsByCodes($first, [$world['students']->first()->username]);
    examCommittee($world, ['name' => 'لجنة أخرى']);

    Livewire::actingAs($world['admin'])
        ->test(Index::class)
        ->set('year_id', $world['year']->id)
        ->set('semester', 'first')
        ->set('search', $world['students']->first()->username)
        ->assertSee('لجنة 1')
        ->assertDontSee('لجنة أخرى');
});

test('the form creates a committee and defaults capacity from the venue', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class)
        ->assertSet('year_id', $world['year']->id)
        ->assertSet('semester', 'first')
        ->set('name', 'لجنة 7')
        ->set('venue_id', $world['venue']->id)
        ->assertSet('capacity', 300)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('exam-schedules.manage', ExamCommittee::firstOrFail()));

    expect(ExamCommittee::firstOrFail()->name)->toBe('لجنة 7');
});

test('the form validates required fields', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class)
        ->assertSet('year_id', $world['year']->id)
        ->assertSet('semester', 'first')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'venue_id' => 'required', 'capacity' => 'required']);
});

test('codes typed in the tag input are added and rejected ones are kept', function () {
    $world = examWorld();
    $committee = examCommittee($world);
    $codes = $world['students']->take(2)->pluck('username');

    Livewire::actingAs($world['admin'])
        ->test(Manage::class, ['committee' => $committee])
        ->set('codeInput', $codes->implode(' ').", BAD-CODE\n")
        ->call('addCodes')
        ->assertSet('codeInput', 'BAD-CODE')
        ->assertSet('codeErrors', ['BAD-CODE' => 'لا يوجد طالب بهذا الكود'])
        ->assertSee($codes->first())
        ->assertSee('أكواد لم تُضف');

    expect($committee->members()->count())->toBe(2);
});

test('a chip removes its student', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    $student = $world['students']->first();

    Livewire::actingAs($world['admin'])
        ->test(Manage::class, ['committee' => $committee])
        ->call('removeStudent', $student->id);

    expect($committee->members()->where('student_id', $student->id)->exists())->toBeFalse();
});

test('exam times are added, edited and deleted from the manage screen', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);

    $component = Livewire::actingAs($world['admin'])
        ->test(Manage::class, ['committee' => $committee])
        ->assertSee('إحصاء (5 طالب)')
        ->set('course_id', $world['courses']['E1']->id)
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->call('saveSession')
        ->assertHasNoErrors()
        ->assertSet('course_id', '');

    $session = $committee->sessions()->firstOrFail();

    $component->call('editSession', $session->id)
        ->assertSet('start_time', '09:00')
        ->set('start_time', '12:00')
        ->set('end_time', '14:00')
        ->call('saveSession');

    expect(substr($session->fresh()->start_time, 0, 5))->toBe('12:00');

    $component->call('deleteSession', $session->id);

    expect($committee->sessions()->count())->toBe(0);
});

test('a business rule violation is shown as a toast and nothing is saved', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    Livewire::actingAs($world['admin'])
        ->test(Manage::class, ['committee' => $committee])
        ->set('course_id', $world['courses']['E1']->id)
        ->set('exam_date', '2026-01-15')
        ->set('start_time', '09:00')
        ->set('end_time', '11:00')
        ->call('saveSession')
        ->assertDispatched('toast');

    expect($committee->sessions()->count())->toBe(0);
});

test('the manage screen publishes the committee', function () {
    $world = examWorld();
    $committee = filledExamCommittee($world);
    examService()->createSession($committee, examSessionAttributes($world));

    Livewire::actingAs($world['admin'])
        ->test(Manage::class, ['committee' => $committee->fresh()])
        ->call('publish');

    expect($committee->fresh()->status)->toBe(ExamSessionStatus::PUBLISHED);
});

test('a viewer without edit permission cannot add students', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('exam_schedules.view');

    Livewire::actingAs($viewer)
        ->test(Manage::class, ['committee' => $committee])
        ->set('codeInput', $world['students']->first()->username)
        ->call('addCodes')
        ->assertForbidden();
});

test('the committee form term cannot be changed from the browser', function () {
    $world = examWorld();

    Livewire::actingAs($world['admin'])
        ->test(Form::class)
        ->set('semester', 'second');
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

test('exam schedule pages render', function () {
    $world = examWorld();
    $committee = examCommittee($world);

    $this->actingAs($world['admin']);

    $this->get(route('exam-schedules.index'))->assertSuccessful();
    $this->get(route('exam-schedules.create'))->assertSuccessful();
    $this->get(route('exam-schedules.edit', $committee))->assertSuccessful();
    $this->get(route('exam-schedules.manage', $committee))->assertSuccessful();
});
