<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Support\ValidationResult;

final readonly class CompiledValidator
{
    private Validator $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = clone $validator;
        $this->validator->freeze();
    }

    /** @param array<int|string,mixed> $data */
    public function validate(array $data): ValidationResult
    {
        return $this->validator->validate($data);
    }
}
