<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MimeTypes Rule - Cost: 30
 */
class MimeTypes extends AbstractMimeTypeListRule
{
    public function cost(): int
    {
        return 70;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these MIME types: "
          . implode(', ', $this->types) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        $mime = $this->resolveMimeType($value);
        if ($mime === null) {
            return false;
        }

        return in_array($mime, $this->types, true);
    }
}
