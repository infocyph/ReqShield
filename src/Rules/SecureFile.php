<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * SecureFile Rule - Cost: 65
 * Composite upload guard that combines file payload and metadata safety checks.
 */
class SecureFile extends BaseRule
{
    private readonly File $fileRule;
    private readonly UploadMeta $uploadMetaRule;

    public function __construct(
        protected ?string $mode = 'success',
        int|string|null $maxFilenameLength = 255,
    ) {
        $this->fileRule = new File();
        $this->uploadMetaRule = new UploadMeta($this->mode, $maxFilenameLength);
    }

    public function cost(): int
    {
        return 65;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a secure uploaded file.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->fileRule->passes($value, $field, $data)
            && $this->uploadMetaRule->passes($value, $field, $data);
    }
}
