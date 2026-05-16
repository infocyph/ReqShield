<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Image Rule - Cost: 25
 */
class Image extends AbstractImageFileRule
{
    public function cost(): int
    {
        return 60;
    }

    public function message(string $field): string
    {
        return "The {$field} must be an image.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return $this->getImageInfo($value) !== false;
    }
}
