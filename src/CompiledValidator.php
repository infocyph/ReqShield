<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Support\ValidationResult;

final readonly class CompiledValidator
{
    private \Closure $validateCallback;

    public function __construct(Validator $validator)
    {
        $this->validateCallback = static fn(array $data): ValidationResult => $validator->validate($data);
    }

    /** @param array<int|string,mixed> $data */
    public function validate(array $data): ValidationResult
    {
        return ($this->validateCallback)($data);
    }
}
