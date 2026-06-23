<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('settings.view'), 403);

        $settings = Setting::query()->first();

        return view('admin.pages.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        abort_unless(auth()->user()->can('settings.edit'), 403);

        $settings = Setting::query()->firstOrCreate();

        $settings->update([
            'card_show_photo' => $request->has('card_show_photo'),
            'card_show_name' => $request->has('card_show_name'),
            'card_show_code' => $request->has('card_show_code'),
            'card_show_barcode' => $request->has('card_show_barcode'),
            'card_show_department' => $request->has('card_show_department'),
            'card_show_section' => $request->has('card_show_section'),
            'card_show_level' => $request->has('card_show_level'),
            'card_show_national_id' => $request->has('card_show_national_id'),

            'seat_show_photo' => $request->has('seat_show_photo'),
            'seat_show_name' => $request->has('seat_show_name'),
            'seat_show_code' => $request->has('seat_show_code'),
            'seat_show_department' => $request->has('seat_show_department'),
            'seat_show_section' => $request->has('seat_show_section'),
            'seat_show_level' => $request->has('seat_show_level'),
            'seat_show_seat_number' => $request->has('seat_show_seat_number'),

            'cert_show_photo' => $request->has('cert_show_photo'),
            'cert_show_birth_info' => $request->has('cert_show_birth_info'),
            'cert_show_national_id' => $request->has('cert_show_national_id'),
            'cert_show_seat_number' => $request->has('cert_show_seat_number'),
            'cert_show_specialization' => $request->has('cert_show_specialization'),
            'cert_show_grade' => $request->has('cert_show_grade'),
            'cert_show_cgpa' => $request->has('cert_show_cgpa'),
            'cert_show_semester' => $request->has('cert_show_semester'),
            'cert_show_extra' => $request->has('cert_show_extra'),

            'ministerial_receipt_start' => $request->ministerial_receipt_start,
            'ministerial_receipt_end' => $request->ministerial_receipt_end,
            'ministerial_receipt_current' => $request->ministerial_receipt_current,
            'military_education_default_fee' => $request->military_education_default_fee,
        ]);

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
