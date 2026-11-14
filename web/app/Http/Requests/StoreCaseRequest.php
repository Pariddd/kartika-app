<?php

namespace App\Http\Requests;

use App\Enums\Platform;
use App\Support\UploadLimit;
use App\Support\YouTubeVideoId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi kasus baru. Batasnya sama dengan docs/kontrak.md bagian 4,
 * dan diperiksa ulang oleh worker Python.
 */
class StoreCaseRequest extends FormRequest
{
    /** @var list<string> */
    private array $videoIds = [];

    public function authorize(): bool
    {
        return $this->user()->can('collect-data');
    }

    /**
     * @return list<string>
     */
    public static function enabledPlatforms(): array
    {
        $imported = config('radar.sources.import_enabled')
            ? array_map(fn (Platform $p) => $p->value, Platform::imported())
            : [];

        return [Platform::YouTube->value, ...$imported];
    }

    /**
     * Jumlah maksimal post boleh dikosongkan: artinya "ambil semua yang tersedia",
     * yang tetap dibatasi batas atas per kasus.
     */
    protected function prepareForValidation(): void
    {
        if (trim((string) $this->input('max_items')) === '') {
            $this->merge(['max_items' => config('radar.collect.max_items')]);
        }
    }

    public function isImport(): bool
    {
        return Platform::tryFrom((string) $this->input('platform'))?->isImported() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isImport = $this->isImport();

        return [
            'name' => ['required', 'string', 'max:200'],
            'platform' => ['required', 'string', Rule::in(self::enabledPlatforms())],
            'max_items' => ['required', 'integer', 'min:1', 'max:'.config('radar.collect.max_items')],
            'video_urls' => [Rule::requiredIf(! $isImport), 'nullable', 'string', 'max:5000'],
            // X, Instagram, TikTok: hasil ekspor JSON dari Apify Console.
            'import_file' => [Rule::requiredIf($isImport), 'nullable', 'file', 'max:'.UploadLimit::importKilobytes(), 'extensions:json,jsonl'],
            'query' => ['nullable', 'string', 'max:'.config('radar.collect.query_max_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kasus wajib diisi.',
            'name.max' => 'Nama kasus maksimal 200 karakter.',
            'platform.required' => 'Pilih sumber data.',
            'platform.in' => 'Sumber data ini belum diaktifkan.',
            'max_items.required' => 'Jumlah maksimal post wajib diisi.',
            'max_items.integer' => 'Jumlah maksimal post harus berupa angka.',
            'max_items.min' => 'Jumlah maksimal post minimal 1.',
            'max_items.max' => 'Jumlah maksimal post per kasus adalah :max. Di atas itu memori analisis tidak cukup.',
            'video_urls.required' => 'Isi minimal satu link atau ID video YouTube.',
            'import_file.required' => 'Unggah file JSON hasil ekspor Apify.',
            'import_file.file' => 'Unggahan file gagal; coba lagi.',
            'import_file.uploaded' => 'File gagal diunggah. Kemungkinan ukurannya melebihi batas unggahan PHP di komputer ini ('.UploadLimit::label().').',
            'import_file.max' => 'Ukuran file melebihi batas '.UploadLimit::label().'.',
            'import_file.extensions' => 'File harus berformat .json atau .jsonl (Export → JSON di Apify).',
            'query.max' => 'Catatan kata kunci maksimal :max karakter.',
        ];
    }

    /**
     * Link video diubah menjadi ID di sisi server; hanya ID yang masuk antrean.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->isImport() || $validator->errors()->has('import_file')) {
                    return;
                }
                // Tebakan MIME tidak dipakai (JSON Lines ditebak berbeda-beda tiap sistem). Penggantinya
                // pemeriksaan murah ini (file bisa ratusan MB): JSON atau JSON Lines diawali [ atau {.
                // File disimpan di luar folder publik, tidak pernah dieksekusi, dan dihapus setelah dibaca.
                // Nama kolom dan isi tiap baris dikenali dan diperiksa oleh worker Python.
                $handle = fopen($this->file('import_file')->getRealPath(), 'rb');
                $head = ltrim((string) fread($handle, 64), "\xEF\xBB\xBF \t\r\n");
                fclose($handle);
                if (! str_starts_with($head, '[') && ! str_starts_with($head, '{')) {
                    $validator->errors()->add('import_file', 'Isi file bukan JSON. Di Apify pilih Export → JSON (bukan CSV atau Excel).');
                }
            },
            function (Validator $validator): void {
                if ($this->input('platform') !== Platform::YouTube->value || $validator->errors()->has('video_urls')) {
                    return;
                }

                $lines = array_values(array_filter(
                    array_map('trim', preg_split('/\R/', (string) $this->input('video_urls'))),
                    fn (string $line): bool => $line !== '',
                ));

                $invalid = [];

                foreach ($lines as $number => $line) {
                    $id = YouTubeVideoId::extract($line);

                    if ($id === null) {
                        $invalid[] = $number + 1;
                    } elseif (! in_array($id, $this->videoIds, true)) {
                        $this->videoIds[] = $id;
                    }
                }

                $maxVideos = config('radar.collect.max_video_ids');

                if ($invalid !== []) {
                    $validator->errors()->add('video_urls', 'Baris '.implode(', ', $invalid).' bukan link atau ID video YouTube yang valid.');
                } elseif ($this->videoIds === []) {
                    $validator->errors()->add('video_urls', 'Isi minimal satu link atau ID video YouTube.');
                } elseif (count($this->videoIds) > $maxVideos) {
                    $validator->errors()->add('video_urls', "Maksimal {$maxVideos} video per kasus.");
                }
            },
        ];
    }

    /**
     * Parameter job collect sesuai kontrak. Hanya dipanggil setelah validasi lolos.
     *
     * @return array<string, mixed>
     */
    public function collectParams(?string $fileRef = null): array
    {
        if ($this->isImport()) {
            return [
                'platform' => (string) $this->input('platform'),
                'file_ref' => $fileRef,
                'max_items' => (int) $this->input('max_items'),
                'query' => trim((string) $this->input('query')) ?: null,
            ];
        }

        return [
            'platform' => Platform::YouTube->value,
            'video_ids' => $this->videoIds,
            'max_items' => (int) $this->input('max_items'),
        ];
    }
}
