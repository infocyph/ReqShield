<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Support\MimeTypeResolver;

class Mimes extends AbstractMimeTypeListRule
{
    public static function clearCache(): void
    {
        MimeTypeResolver::clearCache();
    }

    public function cost(): int
    {
        return 25;
    }

    /** @return array<string, array<int, string>> */
    public function getResolvedMimeTypes(): array
    {
        $resolved = [];
        foreach ($this->types as $extension) {
            $resolved[$extension] = MimeTypeResolver::getMimeTypes($extension);
        }

        return $resolved;
    }

    public function message(string $field): string
    {
        return "The {$field} must be one of these types: " . implode(
            ', ',
            $this->types,
        ) . '.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        $fileMimeType = $this->resolveMimeType($value);
        if ($fileMimeType === null) {
            return false;
        }

        return $this->matchesResolvedMimeType($fileMimeType)
            || in_array($fileMimeType, $this->types, true);
    }

    protected function matchesResolvedMimeType(string $fileMimeType): bool
    {
        foreach ($this->types as $extension) {
            $allowedMimes = MimeTypeResolver::getMimeTypes($extension);
            if (in_array($fileMimeType, $allowedMimes, true)) {
                return true;
            }
        }

        return false;
    }
}
