<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(500)]
#[Bench\Iterations(5)]
final class ImplicitBench
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'mode' => 'required|string',
            'conditional' => 'required_if:mode,active|string',
            'present' => 'present_if:mode,active|string',
            'nullable' => 'nullable|string',
            'optional' => 'string',
        ]);
    }

    #[Bench\Groups(['implicit', 'active'])]
    public function benchActiveConditionalRules(): void
    {
        $this->validator->validate([
            'mode' => 'active',
            'conditional' => 'value',
            'present' => 'value',
            'nullable' => null,
        ]);
    }

    #[Bench\Groups(['implicit', 'inactive'])]
    public function benchInactiveConditionalRules(): void
    {
        $this->validator->validate(['mode' => 'inactive', 'nullable' => null]);
    }
}
