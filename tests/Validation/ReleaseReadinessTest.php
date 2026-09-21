<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Exceptions\InputLimitException;
use Infocyph\ReqShield\Validator;

test('large wildcard validation succeeds within explicit configured limits', function () {
    $rows = array_fill(0, 1_000, [
        'email' => 'valid@example.com',
    ]);

    $result = Validator::make([
        'rows.*.email' => 'required|email',
    ])->limits(
        maxDepth: 8,
        maxFields: 2_100,
        maxWildcardExpansions: 1_100,
        maxFlattenedPaths: 1_100,
    )->validate([
        'rows' => $rows,
    ]);

    expect($result->passes())->toBeTrue();
});

test('wildcard bounds fail before database provider work begins', function () {
    $provider = new class implements DatabaseProvider {
        public int $calls = 0;

        public function batchExists(string $table, array $checks): array
        {
            unset($table, $checks);
            ++$this->calls;

            return [];
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);
            ++$this->calls;

            return [];
        }
    };

    $validator = Validator::make([
        'rows.*.team_id' => 'required|exists:teams,id',
    ], $provider)->limits(
        maxDepth: 8,
        maxFields: 100,
        maxWildcardExpansions: 2,
        maxFlattenedPaths: 100,
    );

    expect(fn() => $validator->validate([
        'rows' => [
            ['team_id' => 1],
            ['team_id' => 2],
            ['team_id' => 3],
        ],
    ]))->toThrow(InputLimitException::class)
        ->and($provider->calls)->toBe(0);
});

test('database infrastructure errors preserve previous exceptions without leaking details', function () {
    $provider = new class implements DatabaseProvider {
        public function batchExists(string $table, array $checks): array
        {
            unset($table, $checks);

            throw new RuntimeException(
                'SELECT * FROM users WHERE token = super-secret-binding',
            );
        }

        public function batchUnique(string $table, array $checks): array
        {
            unset($table, $checks);

            return [];
        }
    };

    $exception = null;

    try {
        Validator::make([
            'team_id' => 'required|exists:teams,id',
        ], $provider)->validate([
            'team_id' => 1,
        ]);
    } catch (DatabaseValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(DatabaseValidationException::class)
        ->and($exception?->getPrevious())->toBeInstanceOf(RuntimeException::class)
        ->and($exception?->getMessage())->not->toContain('SELECT')
        ->and($exception?->getMessage())->not->toContain('super-secret-binding');
});
