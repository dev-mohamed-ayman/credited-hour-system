<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;

use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::query()->first();

        return view('admin.pages.settings', compact('settings'));
    }

    public function update(Request $request)
    {
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
        ]);

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }
}
