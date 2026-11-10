<?php

namespace App\Http\Requests;

use App\Support\CsvFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUploadRequest extends FormRequest
{
    public int $rowCount = 0;

    public function authorize(): bool
    {
        // Semua peran boleh: data milik pengunggah sendiri, tidak ada pengumpulan data pihak ketiga.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.CsvFormat::MAX_KILOBYTES],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file CSV.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
            'name.max' => 'Nama maksimal 200 karakter.',
            'consent.accepted' => 'Centang pernyataan bahwa Anda berhak memakai data ini.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('file')) {
                    return;
                }
                $result = CsvFormat::inspect($this->file('file')->getRealPath());
                foreach ($result['errors'] as $error) {
                    $validator->errors()->add('file', $error);
                }
                $this->rowCount = $result['rows'];
            },
        ];
    }
}
