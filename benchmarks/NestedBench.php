<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(500)]
#[Bench\Iterations(5)]
final class NestedBench
{
    /** @var array<string,mixed> */
    private array $data = [
        'profile' => [
            'contact' => ['email' => 'bench@example.com', 'phone' => '+8801700000000'],
            'address' => ['city' => 'Dhaka', 'postal_code' => '1207'],
        ],
    ];

    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'profile.contact.email' => 'required|email',
            'profile.contact.phone' => 'required|string',
            'profile.address.city' => 'required|string',
            'profile.address.postal_code' => 'required|string',
        ]);
    }

    #[Bench\Groups(['nested', 'targeted-paths'])]
    public function benchTargetedPathTraversal(): void
    {
        $this->validator->validate($this->data);
    }
}
