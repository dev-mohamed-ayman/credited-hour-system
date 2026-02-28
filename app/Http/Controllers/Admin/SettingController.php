<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::query()->first();

        return view('admin.pages.settings', compact('settings'));
    }

    public function update() {}
}
