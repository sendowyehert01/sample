<?php

namespace Http\Validation;

use Core\HttpException;

class SampleValidator
{
    private const MAX_NAME_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 2000;

    /**
     * @return array{0: string, 1: ?string} The trimmed name and description.
     */
    public function validate(mixed $name, mixed $description): array
    {
        $errors = [];

        if (!is_string($name) || trim($name) === '' || strlen(trim($name)) > self::MAX_NAME_LENGTH) {
            $errors['name'] = ['The name field must be a non-empty string of up to ' . self::MAX_NAME_LENGTH . ' bytes.'];
        }

        if ($description !== null && (!is_string($description) || strlen(trim($description)) > self::MAX_DESCRIPTION_LENGTH)) {
            $errors['description'] = ['The description field must be a string of up to ' . self::MAX_DESCRIPTION_LENGTH . ' bytes.'];
        }

        if ($errors !== []) {
            throw new HttpException(422, 'Validation failed.', $errors);
        }

        return [trim($name), $description === null || trim($description) === '' ? null : trim($description)];
    }
}
