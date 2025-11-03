<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * MimeTypes Rule - Cost: 15
 */
class MimeTypes extends BaseRule
{
    protected array $types;

    public function __construct(string ...$types)
    {
        $this->types = $types;
    }

    public function cost(): int
    {
        return 15;
    }

    public function message(string $field): string
    {
        return "The {$field} must have one of these MIME types: " .
          implode(', ', $this->types) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            return false;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $value['tmp_name']);
        finfo_close($finfo);

        return in_array($mime, $this->types, true);
    }

}
