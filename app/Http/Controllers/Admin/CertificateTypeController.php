<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateTypeRequest;
use App\Http\Requests\Admin\UpdateCertificateTypeRequest;
use App\Models\CertificateType;
use App\Models\Section;

class CertificateTypeController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('certificate_types.view'), 403);

        $certificateTypes = CertificateType::with(['requirements.department', 'sections.department'])->latest()->get();

        return view('admin.pages.certificate-type.index', compact('certificateTypes'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('certificate_types.create'), 403);

        $requirements = \App\Models\DepartmentRequirement::with('department')->get();
        $sections = Section::with('department')->get();

        return view('admin.pages.certificate-type.create', compact('requirements', 'sections'));
    }

    public function store(StoreCertificateTypeRequest $request)
    {
        abort_unless(auth()->user()->can('certificate_types.create'), 403);

        $certificateType = CertificateType::create($request->validated());

        $certificateType->requirements()->sync($request->input('requirement_ids', []));
        $certificateType->sections()->sync($request->input('section_ids', []));

        return redirect()->route('certificate-types.index')->with('success', 'تم إضافة الشهادة بنجاح');
    }

    public function edit(CertificateType $certificateType)
    {
        abort_unless(auth()->user()->can('certificate_types.edit'), 403);

        $certificateType->load(['requirements', 'sections']);
        $requirements = \App\Models\DepartmentRequirement::with('department')->get();
        $sections = Section::with('department')->get();

        return view('admin.pages.certificate-type.edit', compact('certificateType', 'requirements', 'sections'));
    }

    public function update(UpdateCertificateTypeRequest $request, CertificateType $certificateType)
    {
        abort_unless(auth()->user()->can('certificate_types.edit'), 403);

        $certificateType->update($request->validated());

        $certificateType->requirements()->sync($request->input('requirement_ids', []));
        $certificateType->sections()->sync($request->input('section_ids', []));

        return redirect()->route('certificate-types.index')->with('success', 'تم تحديث الشهادة بنجاح');
    }

    public function destroy(CertificateType $certificateType)
    {
        abort_unless(auth()->user()->can('certificate_types.delete'), 403);

        if ($certificateType->hasBlockingRelations()) {
            return back()->with('error', $certificateType->getBlockingRelationsMessage());
        }

        $certificateType->delete();

        return back()->with('success', 'تم حذف الشهادة بنجاح');
    }
}
