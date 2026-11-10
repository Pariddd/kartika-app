<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Support\CaseReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaseReportController extends Controller
{
    public function show(Request $request, CaseFile $case): View
    {
        return view('cases.report', [
            'case' => $case,
            'report' => CaseReport::build($case),
            'printedBy' => $request->user(),
            'printedAt' => now(),
        ]);
    }
}
