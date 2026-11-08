<?php

namespace Http\Controllers;

use Core\HttpException;
use Core\Request;
use Core\Response;
use Http\Validation\SampleValidator;
use Repositories\SampleRepository;

class SampleController
{
    public function __construct(
        private SampleRepository $samples,
        private Request $request,
        private SampleValidator $validator
    ) {
    }

    public function index(): Response
    {
        return Response::json(['data' => $this->samples->all()]);
    }

    public function show(string $id): Response
    {
        return Response::json(['data' => $this->findSample($id)]);
    }

    public function store(): Response
    {
        [$name, $description] = $this->validator->validate(
            $this->request->input('name'),
            $this->request->input('description')
        );
        $sample = $this->samples->create($name, $description);

        return Response::json(['data' => $sample], 201, ['Location' => '/samples/' . $sample['id']]);
    }

    public function update(string $id): Response
    {
        $sample = $this->findSample($id);
        [$name, $description] = $this->validator->validate(
            $this->request->input('name'),
            $this->request->input('description')
        );
        $this->samples->update($sample['id'], $name, $description);
        $sample['name'] = $name;
        $sample['description'] = $description;

        return Response::json(['data' => $sample]);
    }

    public function destroy(string $id): Response
    {
        $sample = $this->findSample($id);
        $this->samples->delete($sample['id']);

        return Response::noContent();
    }

    private function findSample(string $id): array
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $sample = $id === false ? null : $this->samples->find($id);

        if ($sample === null) {
            throw new HttpException(404, 'Sample not found.');
        }

        return $sample;
    }
}
