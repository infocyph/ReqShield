<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Dimensions Rule - Cost: 20
 */
class Dimensions extends BaseRule
{

    public function __construct(
        protected string|int|null $minWidth = 0,
        protected string|int|null $minHeight = 0,
        protected string|int|null $maxWidth = 0,
        protected string|int|null $maxHeight = 0,
    ) {}

    public function cost(): int
    {
        return 20;
    }

    public function message(string $field): string
    {
        return "The {$field} must meet dimension requirements.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            return false;
        }
        $imageInfo = @getimagesize($value['tmp_name']);
        if ($imageInfo === false) {
            return false;
        }
        [$width, $height] = $imageInfo;
        if ($this->minWidth && $width < $this->minWidth) {
            return false;
        }
        if ($this->minHeight && $height < $this->minHeight) {
            return false;
        }
        if ($this->maxWidth && $width > $this->maxWidth) {
            return false;
        }
        if ($this->maxHeight && $height > $this->maxHeight) {
            return false;
        }

        return true;
    }

}
