<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * File Rule - Cost: 10
 */
class File extends BaseRule
{
    public function cost(): int
    {
        return 10;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid uploaded file.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_array($value)
          && isset($value['tmp_name'], $value['error'])
          && is_uploaded_file($value['tmp_name'])
          && $value['error'] === UPLOAD_ERR_OK;
    }
}
