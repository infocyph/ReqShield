<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Support\ValidationResult;

final readonly class CompiledValidator
{
    public function __construct(
        private Validator $validator,
    ) {}

    /** @param array<int|string,mixed> $data */
    public function validate(array $data): ValidationResult
    {
        return $this->validator->validate($data);
    }

    public function validator(): Validator
    {
        return $this->validator;
    }
}
