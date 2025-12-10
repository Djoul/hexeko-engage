<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class TranslationWebController extends Controller
{
    public function index(): View
    {
        return view('admin-panel.pages.translations.index');
    }
}
