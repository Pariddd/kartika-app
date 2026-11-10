<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreUploadRequest;
use App\Models\AuditLog;
use App\Models\CaseFile;
use App\Support\CsvFormat;
use App\Support\JobPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Coba Sendiri": analisis CSV milik pengguna. File disimpan privat dengan nama UUID
 * buatan server, dibaca worker, lalu dihapus; kasusnya dihapus otomatis setelah 24 jam.
 */
class UploadController extends Controller
{
    public const RETENTION_HOURS = 24;

    public function create(): View
    {
        return view('upload.create', [
            'maxRows' => CsvFormat::MAX_ROWS,
            'retentionHours' => self::RETENTION_HOURS,
        ]);
    }

    public function store(StoreUploadRequest $request): RedirectResponse
    {
        $user = $request->user();
        $fileRef = 'uploads/'.Str::uuid()->toString().'.csv';
        Storage::disk('local')->put($fileRef, $request->file('file')->get());

        $params = [
            'file_ref' => $fileRef,
            'delete_after_ingest' => true,
            'use_embedding' => false,
        ];

        try {
            $case = DB::transaction(function () use ($request, $user, $params): CaseFile {
                $case = CaseFile::create([
                    // Nama file asli sengaja tidak dipakai: bisa memuat informasi pribadi pengunggah.
                    'name' => $request->filled('name')
                        ? $request->string('name')->trim()->toString()
                        : 'Unggahan CSV '.now()->setTimezone(config('radar.display_timezone'))->format('d-m-Y H.i'),
                    'mode' => CaseMode::Csv,
                    'sources' => ['csv'],
                    'query' => ['rows' => $request->rowCount],
                    'status' => CaseStatus::Analyzing,
                    'created_by' => $user->getKey(),
                    'expires_at' => now()->addHours(self::RETENTION_HOURS),
                ]);

                $case->jobs()->create([
                    'job_type' => JobType::AnalyzeCsv,
                    'status' => JobStatus::Pending,
                    'payload' => JobPayload::make(JobType::AnalyzeCsv, $case, $user, $params),
                ]);

                return $case;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($fileRef);
            throw $exception;
        }

        AuditLog::record('upload.store', $user->getKey(), $case->getKey(), $request->ip());

        return redirect()
            ->route('cases.show', $case)
            ->with('status', "File diterima ({$request->rowCount} baris). Analisis berjalan; halaman ini dimuat ulang otomatis.");
    }

    public function sample(): BinaryFileResponse
    {
        return response()->download(resource_path('samples/contoh-radar-narasi.csv'), 'contoh-radar-narasi.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
