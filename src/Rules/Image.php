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
        $path = $this->getUploadedFilePath($value);
        if ($path === null) {
            return false;
        }

        $imageInfo = @getimagesize($path);

        return $imageInfo !== false;
    }

}
