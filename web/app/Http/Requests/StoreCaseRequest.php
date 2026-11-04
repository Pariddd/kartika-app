<?php

namespace App\Http\Requests;

use App\Enums\Platform;
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
        return config('radar.sources.x_enabled')
            ? [Platform::YouTube->value, Platform::X->value]
            : [Platform::YouTube->value];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isX = $this->input('platform') === Platform::X->value;
        $maxItems = $isX ? config('radar.collect.x_max_items') : config('radar.collect.youtube_max_items');

        return [
            'name' => ['required', 'string', 'max:200'],
            'platform' => ['required', 'string', Rule::in(self::enabledPlatforms())],
            'max_items' => ['required', 'integer', 'min:1', 'max:'.$maxItems],
            'video_urls' => [Rule::requiredIf(! $isX), 'nullable', 'string', 'max:5000'],
            'query' => [Rule::requiredIf($isX), 'nullable', 'string', 'max:'.config('radar.collect.query_max_length')],
            'since' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:since'],
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
            'max_items.max' => 'Jumlah maksimal post untuk sumber ini adalah :max.',
            'video_urls.required' => 'Isi minimal satu link atau ID video YouTube.',
            'query.required' => 'Kata kunci atau tagar wajib diisi.',
            'query.max' => 'Kata kunci maksimal :max karakter.',
            'since.date_format' => 'Format tanggal mulai harus YYYY-MM-DD.',
            'until.date_format' => 'Format tanggal akhir harus YYYY-MM-DD.',
            'until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.',
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
    public function collectParams(): array
    {
        if ($this->input('platform') === Platform::X->value) {
            return [
                'platform' => Platform::X->value,
                'query' => trim((string) $this->input('query')),
                'max_items' => (int) $this->input('max_items'),
                'since' => $this->input('since') ?: null,
                'until' => $this->input('until') ?: null,
            ];
        }

        return [
            'platform' => Platform::YouTube->value,
            'video_ids' => $this->videoIds,
            'max_items' => (int) $this->input('max_items'),
        ];
    }
}
