<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class SanitizerBench
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'email' => [
                'rules' => 'required|email',
                'sanitize' => ['trim', 'lowercase'],
            ],
        ]);
    }

    #[Bench\Groups(['sanitizer', 'compiled-pipeline'])]
    public function benchCompiledPipeline(): void
    {
        $this->validator->validate(['email' => '  BENCH@example.com  ']);
    }
}
