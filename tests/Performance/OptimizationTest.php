<?php

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Rules\Exists;
use Infocyph\ReqShield\Rules\Unique;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Validator;

test('nested required flatten mode validates configured paths only', function () {
    $validator = Validator::make([
        'user.email' => 'required|email',
        'user.profile.age' => 'required|integer|min:18',
    ])->enableNestedValidation(false);

    $result = $validator->validate([
        'user' => [
            'email' => 'john@example.com',
            'profile' => [
                'age' => 25,
                'extra' => [
                    'big' => ['deep' => 'value'],
                ],
            ],
        ],
    ]);

    expect($result->passes())->toBeTrue();
    expect($result->validated())->toHaveKeys(['user.email', 'user.profile.age']);
});

test('flattenForPaths keeps only requested nested paths', function () {
    $data = [
        'user' => [
            'email' => 'john@example.com',
            'profile' => [
                'age' => 30,
                'bio' => 'Developer',
            ],
        ],
        'status' => 'active',
    ];

    $flattened = NestedValidator::flattenForPaths($data, [
        'user.email',
        'user.profile.age',
        'status',
    ]);

    expect($flattened)->toEqual([
        'user.email' => 'john@example.com',
        'user.profile.age' => 30,
        'status' => 'active',
    ]);
});

test('shapeSignature tracks structure but ignores scalar values', function () {
    $a = [
        'user' => [
            'email' => 'john@example.com',
            'profile' => ['age' => 30],
        ],
    ];
    $b = [
        'user' => [
            'email' => 'jane@example.com',
            'profile' => ['age' => 40],
        ],
    ];
    $c = [
        'user' => [
            'email' => 'jane@example.com',
            'profile' => ['age' => 40, 'bio' => 'extra'],
        ],
    ];

    expect(NestedValidator::shapeSignature($a))
        ->toBe(NestedValidator::shapeSignature($b))
        ->and(NestedValidator::shapeSignature($a))
        ->not->toBe(NestedValidator::shapeSignature($c));
});

test('batch executor groups checks by table and uses structured batch payloads', function () {
    $provider = new class implements DatabaseProvider {
        public array $existsPayloads = [];
        public int $queryCalls = 0;

        public function batchExistsCheck(string $table, array $checks): array
        {
            $this->existsPayloads[] = ['table' => $table, 'checks' => $checks];

            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            return [];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return false;
        }

        public function query(string $query, array $params = []): array
        {
            $this->queryCalls++;

            return [];
        }
    };

    $executor = new BatchExecutor($provider);
    $errors = [];
    $batch = [
        [
            'rule' => new Exists('users', 'email'),
            'value' => 'a@example.com',
            'field' => 'email_a',
        ],
        [
            'rule' => new Exists('users', 'email'),
            'value' => 'a@example.com',
            'field' => 'email_b',
        ],
        [
            'rule' => new Exists('users', 'username'),
            'value' => 'sam',
            'field' => 'username',
        ],
    ];

    $executor->executeBatch($batch, $errors);

    expect($provider->queryCalls)->toBe(0);
    expect($provider->existsPayloads)->toHaveCount(1);
    expect($provider->existsPayloads[0]['table'])->toBe('users');
    expect($provider->existsPayloads[0]['checks'])->toHaveCount(3);
    expect($provider->existsPayloads[0]['checks'][0])->toHaveKeys([
        'column',
        'value',
        'field',
    ]);
});

test('batch executor chunks large batch payloads', function () {
    $provider = new class implements DatabaseProvider {
        public array $existsPayloads = [];

        public function batchExistsCheck(string $table, array $checks): array
        {
            $this->existsPayloads[] = ['table' => $table, 'checks' => $checks];

            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            return [];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return false;
        }

        public function query(string $query, array $params = []): array
        {
            return [];
        }
    };

    $executor = new BatchExecutor($provider);
    $errors = [];
    $batch = [];

    foreach (range(1, 501) as $index) {
        $batch[] = [
            'rule' => new Exists('users', 'email'),
            'value' => "user{$index}@example.com",
            'field' => "email_{$index}",
        ];
    }

    $executor->executeBatch($batch, $errors);

    expect($provider->existsPayloads)->toHaveCount(2);
    expect($provider->existsPayloads[0]['checks'])->toHaveCount(500);
    expect($provider->existsPayloads[1]['checks'])->toHaveCount(1);
});

test('batch executor records failures returned by provider batch methods', function () {
    $provider = new class implements DatabaseProvider {
        public int $existsCalls = 0;
        public int $uniqueCalls = 0;

        public function batchExistsCheck(string $table, array $checks): array
        {
            $this->existsCalls++;

            return ['missing_email'];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            $this->uniqueCalls++;

            return ['taken_email'];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            return false;
        }

        public function query(string $query, array $params = []): array
        {
            throw new RuntimeException('query() should not be used by batch executor');
        }
    };

    $executor = new BatchExecutor($provider);
    $errors = [];
    $batch = [
        [
            'rule' => new Exists('users', 'email'),
            'value' => 'missing@example.com',
            'field' => 'missing_email',
            'rule_name' => 'exists',
            'message' => 'The selected Email is invalid.',
        ],
        [
            'rule' => new Unique('users', 'email'),
            'value' => 'taken@example.com',
            'field' => 'taken_email',
            'rule_name' => 'unique',
            'message' => 'The Email has already been taken.',
        ],
    ];

    $executor->executeBatch($batch, $errors);

    expect($provider->existsCalls)->toBe(1);
    expect($provider->uniqueCalls)->toBe(1);
    expect($errors)->toHaveKeys(['missing_email', 'taken_email']);
});
