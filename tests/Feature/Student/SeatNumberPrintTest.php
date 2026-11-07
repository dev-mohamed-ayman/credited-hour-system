<?php

use App\Enums\Semester;
use App\Models\ExamCommittee;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->world = billingWorld();
    $this->student = $this->world['student'];

    Setting::query()->firstOrCreate([])->update([
        'seat_show_photo' => false,
        'seat_show_name' => true,
        'seat_show_code' => true,
        'seat_show_department' => true,
        'seat_show_section' => true,
        'seat_show_level' => true,
        'seat_show_seat_number' => true,
    ]);
});

function seatInCommittee(array $world, Student $student, int $seat, bool $published = true): void
{
    $factory = ExamCommittee::factory();

    $committee = ($published ? $factory->published() : $factory)->create([
        'year_id' => $world['year']->id,
        'semester' => Semester::FIRST,
        'name' => 'لجنة '.$seat,
    ]);

    $committee->members()->create(['student_id' => $student->id, 'seat_number' => $seat]);
}

it('shows the logged-in student their current term committee seat card', function () {
    seatInCommittee($this->world, $this->student, 99887766);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertSuccessful()
        ->assertSee('بطاقة رقم الجلوس')
        ->assertSee('99887766')
        ->assertSee('لجنة 99887766')
        ->assertSee($this->student->name)
        ->assertSee($this->student->username)
        ->assertSee('علوم حاسب');
});

it('ignores the high school seat number stored on the student', function () {
    $this->student->update(['seat_number' => 'HS-123']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.dashboard'));
});

it('bounces the student back with a clear message when no seat number is assigned yet', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.dashboard'))
        ->assertSessionHas('error', 'لم يتم تخصيص رقم جلوس لك بعد — تابع الشؤون الطلابية.');
});

it('waits for the committee to be published', function () {
    seatInCommittee($this->world, $this->student, 777, published: false);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.dashboard'));
});

it('honours the seat card display settings', function () {
    seatInCommittee($this->world, $this->student, 555);

    Setting::query()->update(['seat_show_name' => false, 'seat_show_department' => false]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertSuccessful()
        ->assertSee('555')
        ->assertSee('كود الطالب')
        ->assertDontSee('الاسم:')
        ->assertDontSee('التخصص:');
});

it('never renders another student card — the page is tied to the session', function () {
    seatInCommittee($this->world, $this->student, 11111);

    $other = seatCardStudent($this->world, 'طالب تاني', 'OTHER01');
    seatInCommittee($this->world, $other, 22222);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertSee('11111')
        ->assertDontSee('22222');
});

it('sends guests to the student login', function () {
    $this->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.login'));
});
