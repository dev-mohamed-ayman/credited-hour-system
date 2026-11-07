<?php

use App\Models\Department;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['sections.view', 'sections.create', 'sections.edit'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo(['sections.view', 'sections.create', 'sections.edit']);
    $this->department = Department::create(['name' => 'علوم حاسب', 'code' => 'CS']);
});

it('no longer has the unused cgpa column on sections', function () {
    expect(Schema::hasColumn('sections', 'cgpa'))->toBeFalse();
});

it('creates a section from its name and department only', function () {
    $this->actingAs($this->admin)
        ->post(route('sections.store'), ['name' => 'شعبة أ', 'department_id' => $this->department->id])
        ->assertRedirect(route('sections.index'));

    expect(Section::where('name', 'شعبة أ')->where('department_id', $this->department->id)->exists())->toBeTrue();
});

it('shows the section forms without a cgpa field', function () {
    $section = Section::create(['name' => 'شعبة أ', 'department_id' => $this->department->id]);

    $this->actingAs($this->admin)->get(route('sections.create'))->assertSuccessful()->assertDontSee('CGPA');
    $this->actingAs($this->admin)->get(route('sections.edit', $section))->assertSuccessful()->assertDontSee('CGPA');
    $this->actingAs($this->admin)->get(route('sections.index'))->assertSuccessful()->assertDontSee('CGPA');
});
