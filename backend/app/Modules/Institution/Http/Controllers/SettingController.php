<?php

namespace App\Modules\Institution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Institution\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingController extends Controller
{
    /** Public: the sign-in page needs the name, logo and colours before anyone signs in. */
    public function branding(): JsonResponse
    {
        return response()->json(['data' => Setting::where('key', 'branding')->value('value') ?? []]);
    }

    public function updateBranding(Request $request): JsonResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'portal_title' => ['required', 'string', 'max:60'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => ['nullable', 'url', 'max:500'],
        ]);
        Setting::updateOrCreate(['key' => 'branding'], ['value' => $data]);

        return response()->json(['data' => $data]);
    }
}
