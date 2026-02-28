<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with('requirements')->latest()->get();

        return view('admin.pages.department.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.pages.department.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'requirements' => 'nullable|array',
            'requirements.*.subject_name' => 'nullable|string|max:255',
            'requirements.*.min_score' => 'nullable|numeric|min:0',
        ]);

        $department = Department::create([
            'name' => $request->name,
            'code' => $request->code,
        ]);

        if ($request->has('requirements')) {
            foreach ($request->requirements as $requirement) {
                // حفظ فقط المتطلبات التي تحتوي على اسم المادة والدرجة
                if (! empty($requirement['subject_name']) && isset($requirement['min_score'])) {
                    $department->requirements()->create($requirement);
                }
            }
        }

        return redirect()->route('departments.index')->with('success', 'تم إضافة التخصص بنجاح');
    }

    public function edit(Department $department)
    {
        $department->load('requirements');

        return view('admin.pages.department.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code,'.$department->id,
            'requirements' => 'nullable|array',
            'requirements.*.subject_name' => 'nullable|string|max:255',
            'requirements.*.min_score' => 'nullable|numeric|min:0',
        ]);

        $department->update([
            'name' => $request->name,
            'code' => $request->code,
        ]);

        // حذف المتطلبات القديمة وإعادة إضافة المعبأة فقط
        $department->requirements()->delete();

        if ($request->has('requirements')) {
            foreach ($request->requirements as $requirement) {
                if (! empty($requirement['subject_name']) && isset($requirement['min_score'])) {
                    $department->requirements()->create($requirement);
                }
            }
        }

        return redirect()->route('departments.index')->with('success', 'تم تحديث التخصص بنجاح');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with('success', 'تم حذف التخصص بنجاح');
    }
}
