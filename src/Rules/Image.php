<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Image Rule - Cost: 15
 */
class Image extends BaseRule
{
    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must be an image.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            return false;
        }
        $imageInfo = @getimagesize($value['tmp_name']);

        return $imageInfo !== false;
    }

}
