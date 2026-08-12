<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class CastingBench
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'age' => ['rules' => 'required|numeric', 'cast' => 'integer'],
            'enabled' => ['rules' => 'required', 'cast' => 'boolean'],
            'settings' => ['rules' => 'required|json', 'cast' => 'json'],
        ]);
    }

    #[Bench\Groups(['casting', 'compiled-pipeline'])]
    public function benchCompiledCasts(): void
    {
        $this->validator->validate([
            'age' => '42',
            'enabled' => 'true',
            'settings' => '{"theme":"dark"}',
        ])->typed();
    }
}
