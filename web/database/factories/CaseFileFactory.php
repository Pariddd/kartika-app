<?php

namespace Database\Factories;

use App\Enums\CaseMode;
use App\Enums\CaseStatus;
use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseFile>
 */
class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kasus '.fake()->words(3, true),
            'mode' => CaseMode::Demo,
            'sources' => ['synthetic'],
            'query' => null,
            'status' => CaseStatus::Done,
            'created_by' => User::factory(),
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => CaseMode::Live,
            'sources' => ['youtube'],
            'query' => ['platform' => 'youtube', 'video_ids' => ['dQw4w9WgXcQ'], 'max_items' => 100],
        ]);
    }
}
