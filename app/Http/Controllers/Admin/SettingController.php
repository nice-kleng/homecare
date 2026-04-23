<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::orderBy('group')->orderBy('sort_order')->get()
            ->groupBy('group')
            ->map(fn($group) => $group->map(fn($s) => [
                'id'          => $s->id,
                'key'         => $s->key,
                'value'       => $s->value,
                'type'        => $s->type,
                'label'       => $s->label,
                'description' => $s->description,
            ]));

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings'         => ['required', 'array'],
            'settings.*.key'   => ['required', 'string', 'exists:settings,key'],
            'settings.*.value' => ['nullable'],
        ]);

        foreach ($request->settings as $item) {
            Setting::where('key', $item['key'])
                   ->update(['value' => $item['value']]);
        }

        // Bersihkan cache setting
        \Illuminate\Support\Facades\Cache::forget('app_settings');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
