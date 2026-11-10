<?php

namespace App\Http\Requests;

use App\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClusterReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hak melihat kasus sudah diperiksa middleware can:view,case.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verdict' => ['required', Rule::enum(Verdict::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verdict.required' => 'Pilih salah satu penilaian.',
            'verdict.enum' => 'Penilaian tidak dikenal.',
            'note.max' => 'Catatan maksimal 1.000 karakter.',
        ];
    }
}
