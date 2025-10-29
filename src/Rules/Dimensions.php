<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Dimensions Rule - Cost: 20
 */
class Dimensions extends BaseRule
{
    protected ?int $maxHeight;

    protected ?int $maxWidth;

    protected ?int $minHeight;

    protected ?int $minWidth;

    public function __construct(?int $minWidth = null, ?int $minHeight = null, ?int $maxWidth = null, ?int $maxHeight = null)
    {
        $this->minWidth = $minWidth;
        $this->minHeight = $minHeight;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }

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
        if (! is_array($value) || ! isset($value['tmp_name'])) {
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
