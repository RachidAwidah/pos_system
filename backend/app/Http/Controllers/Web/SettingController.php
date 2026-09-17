<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'settingGroups' => Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group'),
        ]);
    }
}
