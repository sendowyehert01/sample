<?php

namespace Repositories;

use Models\Sample;

class SampleRepository
{
    public function all(): array
    {
        return Sample::orderBy('id')->get()->map($this->record(...))->all();
    }

    public function find(int $id): ?array
    {
        $sample = Sample::find($id);

        return $sample === null ? null : $this->record($sample);
    }

    public function create(string $name, ?string $description): array
    {
        return $this->record(Sample::create(['name' => $name, 'description' => $description]));
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $sample = Sample::find($id);

        if ($sample === null) {
            return;
        }

        $sample->name = $name;
        $sample->description = $description;
        $sample->save();
    }

    public function delete(int $id): void
    {
        Sample::destroy($id);
    }

    /**
     * @return array{id: int, name: string, description: ?string}
     */
    private function record(Sample $sample): array
    {
        return [
            'id' => (int) $sample->id,
            'name' => (string) $sample->name,
            'description' => $sample->description === null ? null : (string) $sample->description,
        ];
    }
}
