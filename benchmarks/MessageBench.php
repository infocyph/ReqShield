<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class MessageBench
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'contacts.*.email' => 'required|email|max:255',
        ])->setCustomMessages([
            'contacts.*.email.email' => 'Each :attribute must be a valid email address.',
        ])->setFieldAliases([
            'contacts.*.email' => 'contact email',
        ]);
    }

    #[Bench\Groups(['messages', 'wildcard-failure'])]
    public function benchWildcardFailureMessage(): void
    {
        $this->validator->validate(['contacts' => [['email' => 'invalid']]]);
    }
}
