<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Database\MockDatabaseProvider;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(200)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class ValidatorBench
{
    /**
     * @var array<string,mixed>
     */
    private array $databasePayload;

    private Validator $databaseValidator;

    /**
     * @var array<string,mixed>
     */
    private array $flatPayload;
    private Validator $flatValidator;

    /**
     * @var array<string,mixed>
     */
    private array $nestedPayload;

    private Validator $nestedValidator;

    public function __construct()
    {
        $this->flatPayload = [
            'email' => 'bench@example.com',
            'username' => 'bench_user',
            'age' => 31,
            'status' => 'active',
            'country' => 'US',
            'zipcode' => '90210',
            'score' => 88,
            'newsletter' => 'yes',
            'profile' => '{"ok":true}',
        ];

        $this->nestedPayload = [
            'users' => [
                [
                    'email' => 'a@example.com',
                    'age' => 22,
                    'tags' => ['alpha', 'beta'],
                ],
                [
                    'email' => 'b@example.com',
                    'age' => 25,
                    'tags' => ['gamma'],
                ],
                [
                    'email' => 'c@example.com',
                    'age' => 29,
                    'tags' => ['delta', 'epsilon', 'zeta'],
                ],
            ],
        ];

        $this->databasePayload = [
            'email' => 'fresh@example.com',
            'username' => 'fresh_user',
            'backup_email' => 'fresh-backup@example.com',
            'team_id' => 10,
            'team_code' => 'ENG',
        ];

        $this->flatValidator = Validator::make([
            'email' => 'required|email|max:255',
            'username' => 'required|string|min:3|max:50|alpha_dash',
            'age' => 'required|integer|min:18|max:120',
            'status' => 'required|in:active,inactive,pending',
            'country' => 'required|string|size:2',
            'zipcode' => 'required|string|min:5|max:10',
            'score' => 'required|integer|min:0|max:100',
            'newsletter' => 'accepted',
            'profile' => 'json',
        ]);

        $this->nestedValidator = Validator::make([
            'users.*.email' => 'required|email',
            'users.*.age' => 'required|integer|min:18',
            'users.*.tags.*' => 'required|string|min:2|max:20',
        ])->enableNestedValidation();

        $databaseProvider = new MockDatabaseProvider();
        $databaseProvider->addData('users', [
            ['id' => 1, 'email' => 'existing@example.com', 'username' => 'existing_user'],
            ['id' => 2, 'email' => 'used@example.com', 'username' => 'used_user'],
        ]);
        $databaseProvider->addData('teams', [
            ['id' => 10, 'code' => 'ENG'],
            ['id' => 20, 'code' => 'OPS'],
        ]);

        $this->databaseValidator = Validator::make([
            'email' => 'required|email|unique:users,email',
            'username' => 'required|alpha_dash|unique:users,username',
            'backup_email' => 'required|email|unique:users,email',
            'team_id' => 'required|exists:teams,id',
            'team_code' => 'required|exists:teams,code',
        ], $databaseProvider);
    }

    #[Bench\Groups(['validator', 'db-heavy-batched'])]
    public function benchDatabaseHeavyBatched(): void
    {
        $this->databaseValidator->validate($this->databasePayload);
    }

    #[Bench\Groups(['validator', 'flat-fast-rules'])]
    public function benchFlatFastRules(): void
    {
        $this->flatValidator->validate($this->flatPayload);
    }

    #[Bench\Groups(['validator', 'nested-wildcard'])]
    public function benchNestedWildcard(): void
    {
        $this->nestedValidator->validate($this->nestedPayload);
    }
}
