<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman depan publik: menjelaskan apa yang dideteksi SyncTrace dan batasnya.
 * Tidak memuat data kasus apa pun; angka evaluasi dibaca dari berkas hasil uji sintetis.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('cases.index');
        }

        return view('home', ['evaluation' => EvaluationController::summary()]);
    }
}
