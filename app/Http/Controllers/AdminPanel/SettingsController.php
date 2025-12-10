<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Settings\General\LocalizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function updateAvailableLocales(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'available_locales' => 'required|array|min:1',
            'available_locales.*' => 'string',
        ]);

        $locSettings = app(LocalizationSettings::class);
        $locSettings->available_locales = $validated['available_locales'];
        $locSettings->save();

        return redirect()
            ->route('admin.manager.translations.index')
            ->with('success', 'Available languages updated successfully!');
    }
}
