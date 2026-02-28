<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateTypeRequest;
use App\Http\Requests\Admin\UpdateCertificateTypeRequest;
use App\Models\CertificateType;

class CertificateTypeController extends Controller
{
    public function index()
    {
        $certificateTypes = CertificateType::with('requirements.department')->latest()->get();

        return view('admin.pages.certificate-type.index', compact('certificateTypes'));
    }

    public function create()
    {
        $requirements = \App\Models\DepartmentRequirement::with('department')->get();

        return view('admin.pages.certificate-type.create', compact('requirements'));
    }

    public function store(StoreCertificateTypeRequest $request)
    {
        $certificateType = CertificateType::create($request->validated());

        $certificateType->requirements()->sync($request->input('requirement_ids', []));

        return redirect()->route('certificate-types.index')->with('success', 'تم إضافة الشهادة بنجاح');
    }

    public function edit(CertificateType $certificateType)
    {
        $certificateType->load('requirements');
        $requirements = \App\Models\DepartmentRequirement::with('department')->get();

        return view('admin.pages.certificate-type.edit', compact('certificateType', 'requirements'));
    }

    public function update(UpdateCertificateTypeRequest $request, CertificateType $certificateType)
    {
        $certificateType->update($request->validated());

        $certificateType->requirements()->sync($request->input('requirement_ids', []));

        return redirect()->route('certificate-types.index')->with('success', 'تم تحديث الشهادة بنجاح');
    }

    public function destroy(CertificateType $certificateType)
    {
        $certificateType->delete();

        return back()->with('success', 'تم حذف الشهادة بنجاح');
    }
}
