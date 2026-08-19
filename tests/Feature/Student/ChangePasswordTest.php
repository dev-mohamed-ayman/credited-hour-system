<?php

use App\Livewire\Student\ChangePassword;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function studentWithPassword(string $password = 'OldPass@1'): Student
{
    return Student::factory()->create([
        'password' => $password,
        'plain_password' => $password,
    ]);
}

test('guests cannot access the change password page', function () {
    $this->get(route('student.change-password'))
        ->assertRedirect(route('student.login'));
});

test('authenticated students can access the change password page', function () {
    $this->actingAs(studentWithPassword(), 'student')
        ->get(route('student.change-password'))
        ->assertStatus(200);
});

test('student can change their password with valid data', function () {
    $student = studentWithPassword();

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'OldPass@1')
        ->set('password', 'NewPass@2')
        ->set('password_confirmation', 'NewPass@2')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSet('current_password', '')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    $student->refresh();

    expect(Hash::check('NewPass@2', $student->password))->toBeTrue()
        ->and($student->plain_password)->toBe('NewPass@2');
});

test('student cannot change their password with a wrong current password', function () {
    $student = studentWithPassword();

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'WrongPass@1')
        ->set('password', 'NewPass@2')
        ->set('password_confirmation', 'NewPass@2')
        ->call('updatePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('OldPass@1', $student->refresh()->password))->toBeTrue();
});

test('the confirmation must match the new password', function () {
    $student = studentWithPassword();

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'OldPass@1')
        ->set('password', 'NewPass@2')
        ->set('password_confirmation', 'NewPass@3')
        ->call('updatePassword')
        ->assertHasErrors(['password' => 'confirmed']);
});

test('the new password must differ from the current one', function () {
    $student = studentWithPassword();

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'OldPass@1')
        ->set('password', 'OldPass@1')
        ->set('password_confirmation', 'OldPass@1')
        ->call('updatePassword')
        ->assertHasErrors(['password' => 'different']);
});

test('the new password is rejected when it breaks the complexity rules', function (string $password) {
    $student = studentWithPassword();

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'OldPass@1')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('updatePassword')
        ->assertHasErrors('password');

    expect(Hash::check('OldPass@1', $student->refresh()->password))->toBeTrue();
})->with([
    'too short' => 'Pa@1abc',
    'no upper case' => 'newpass@2',
    'no lower case' => 'NEWPASS@2',
    'no number' => 'NewPassw@rd',
    'no special character' => 'NewPass22',
    'unsupported special character' => 'NewPass&2',
]);

test('every allowed special character is accepted', function (string $special) {
    $student = studentWithPassword();
    $password = 'NewPass1'.$special;

    Livewire::actingAs($student, 'student')
        ->test(ChangePassword::class)
        ->set('current_password', 'OldPass@1')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check($password, $student->refresh()->password))->toBeTrue();
})->with(str_split('@_#$%/*-+?.'));
