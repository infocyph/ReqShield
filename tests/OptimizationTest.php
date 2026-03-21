<?php

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Rules\Exists;
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

test('batch executor dedupes values and splits queries by column', function () {
    $provider = new class implements DatabaseProvider {
        public array $queries = [];

        public function batchExistsCheck(string $table, array $checks): array
        {
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
            $this->queries[] = ['query' => $query, 'params' => $params];

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

    expect($provider->queries)->toHaveCount(2);
    expect($provider->queries[0]['query'])->not->toContain(' OR ');
    expect($provider->queries[1]['query'])->not->toContain(' OR ');
    expect($provider->queries[0]['params'])->toEqual(['a@example.com']);
    expect($provider->queries[1]['params'])->toEqual(['sam']);
});

test('batch executor chunks large in lists', function () {
    $provider = new class implements DatabaseProvider {
        public array $queries = [];

        public function batchExistsCheck(string $table, array $checks): array
        {
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
            $this->queries[] = ['query' => $query, 'params' => $params];

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

    expect($provider->queries)->toHaveCount(2);
    expect($provider->queries[0]['params'])->toHaveCount(500);
    expect($provider->queries[1]['params'])->toHaveCount(1);
});
