<?php

use App\Models\Setting;
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

it('shows the logged-in student their own printable seat card', function () {
    $this->student->update(['seat_number' => '99887766']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertSuccessful()
        ->assertSee('بطاقة رقم الجلوس')
        ->assertSee('99887766')
        ->assertSee($this->student->name)
        ->assertSee($this->student->username)
        ->assertSee('علوم حاسب');
});

it('bounces the student back with a clear message when no seat number is assigned yet', function () {
    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.dashboard'))
        ->assertSessionHas('error', 'لم يتم تخصيص رقم جلوس لك بعد — تابع الشؤون الطلابية.');
});

it('honours the seat card display settings', function () {
    $this->student->update(['seat_number' => '555']);

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
    $this->student->update(['seat_number' => '11111']);

    $other = seatCardStudent($this->world, 'طالب تاني', 'OTHER01');
    $other->update(['seat_number' => '22222']);

    $this->actingAs($this->student, 'student')
        ->get(route('student.print-seat-number'))
        ->assertSee('11111')
        ->assertDontSee('22222');
});

it('sends guests to the student login', function () {
    $this->get(route('student.print-seat-number'))
        ->assertRedirect(route('student.login'));
});

function seatCardStudent(array $world, string $name, string $username): \App\Models\Student
{
    return \App\Models\Student::create([
        'name' => $name,
        'certificate_type_id' => $world['student']->certificate_type_id,
        'national_id' => fake()->unique()->numerify('##############'),
        'username' => $username,
        'password' => bcrypt('password'),
        'plain_password' => 'password',
        'section_id' => $world['section']->id,
        'level_id' => $world['level']->id,
        'year_id' => $world['year']->id,
        'semester' => \App\Enums\Semester::FIRST->value,
    ]);
}
