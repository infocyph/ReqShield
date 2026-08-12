<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Tests\Fixtures\Database\MockDatabaseProvider;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(200)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class ValidatorBench
{
    private Validator $collectAllValidator;

    /**
     * @var array<string,mixed>
     */
    private array $databasePayload;

    private Validator $databaseValidator;

    private Validator $failureFinalValidator;

    private Validator $failureFirstValidator;

    private Validator $failureMiddleValidator;

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

    /** @var array<int,array<string,string>> */
    private array $scalingPayloads = [];

    /** @var array<int,Validator> */
    private array $scalingValidators = [];

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

        foreach ([1, 10, 50, 100] as $size) {
            $schema = [];
            $payload = [];
            for ($index = 0; $index < $size; ++$index) {
                $schema["field_{$index}"] = 'required|string|max:255';
                $payload["field_{$index}"] = 'value';
            }

            $this->scalingValidators[$size] = Validator::make($schema);
            $this->scalingPayloads[$size] = $payload;
        }

        $this->failureFirstValidator = Validator::make(['value' => 'integer|min:10|max:20']);
        $this->failureMiddleValidator = Validator::make(['value' => 'required|integer|min:10|max:20']);
        $this->failureFinalValidator = Validator::make(['value' => 'required|integer|min:1|max:20']);
        $this->collectAllValidator = Validator::make([
            'first' => 'required|integer',
            'second' => 'required|email',
            'third' => 'required|boolean',
        ])->setFailFast(false);
    }

    #[Bench\Groups(['validator', 'collect-all'])]
    public function benchCollectAllFailures(): void
    {
        $this->collectAllValidator->validate([
            'first' => 'invalid',
            'second' => 'invalid',
            'third' => 'invalid',
        ]);
    }

    #[Bench\Groups(['validator', 'db-heavy-batched'])]
    public function benchDatabaseHeavyBatched(): void
    {
        $this->databaseValidator->validate($this->databasePayload);
    }

    #[Bench\Groups(['validator', 'failure-final-rule'])]
    public function benchFailureFinalRule(): void
    {
        $this->failureFinalValidator->validate(['value' => 21]);
    }

    #[Bench\Groups(['validator', 'failure-first-rule'])]
    public function benchFailureFirstRule(): void
    {
        $this->failureFirstValidator->validate(['value' => 'invalid']);
    }

    #[Bench\Groups(['validator', 'failure-middle-rule'])]
    public function benchFailureMiddleRule(): void
    {
        $this->failureMiddleValidator->validate(['value' => 5]);
    }

    #[Bench\Groups(['validator', 'flat-fast-rules'])]
    public function benchFlatFastRules(): void
    {
        $this->flatValidator->validate($this->flatPayload);
    }

    #[Bench\Groups(['validator', 'flat-50'])]
    public function benchFlatFiftyFields(): void
    {
        $this->scalingValidators[50]->validate($this->scalingPayloads[50]);
    }

    #[Bench\Groups(['validator', 'flat-1'])]
    public function benchFlatOneField(): void
    {
        $this->scalingValidators[1]->validate($this->scalingPayloads[1]);
    }

    #[Bench\Groups(['validator', 'flat-100'])]
    public function benchFlatOneHundredFields(): void
    {
        $this->scalingValidators[100]->validate($this->scalingPayloads[100]);
    }

    #[Bench\Groups(['validator', 'flat-10'])]
    public function benchFlatTenFields(): void
    {
        $this->scalingValidators[10]->validate($this->scalingPayloads[10]);
    }

    #[Bench\Groups(['validator', 'nested-wildcard'])]
    public function benchNestedWildcard(): void
    {
        $this->nestedValidator->validate($this->nestedPayload);
    }
}
