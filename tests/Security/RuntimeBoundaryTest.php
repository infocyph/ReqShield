<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Validator;

test('ordinary strings are not globally blacklisted as process function names', function () {
    foreach (['exec', 'system', 'shell_exec', 'proc_open', 'pcntl_fork', 'posix_kill'] as $value) {
        $result = Validator::make([
            'operation' => 'required|string',
        ])->validate([
            'operation' => $value,
        ]);

        expect($result->passes())->toBeTrue()
            ->and($result->validated()['operation'])->toBe($value);
    }
});

test('normal sanitizers do not rewrite dangerous-function-like substrings', function () {
    $result = Validator::make([
        'note' => 'required|string',
    ])->setSanitizers([
        'note' => ['trim'],
    ])->validate([
        'note' => '  exec system pcntl_fork posix_kill  ',
    ]);

    expect($result->passes())->toBeTrue()
        ->and($result->typed()['note'])->toBe('exec system pcntl_fork posix_kill');
});

test('registered operation identifiers and structured arguments validate without execution', function () {
    $executed = false;
    $validator = Validator::make([
        'operation' => 'required|in:deploy,restart',
        'arguments.width' => 'required|integer|min:1|max:4096',
    ]);

    expect($validator->validate([
        'operation' => 'deploy',
        'arguments' => ['width' => 512],
    ])->passes())->toBeTrue()
        ->and($validator->validate([
            'operation' => 'system',
            'arguments' => ['width' => 512],
        ])->fails())->toBeTrue()
        ->and($validator->validate([
            'operation' => 'deploy',
            'arguments' => ['width' => 8192],
        ])->fails())->toBeTrue()
        ->and($executed)->toBeFalse();
});

test('non database operation validation stays database cold', function () {
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

    $result = Validator::make([
        'operation' => 'required|in:deploy,restart',
    ], $provider)->validate([
        'operation' => 'deploy',
    ]);

    expect($result->passes())->toBeTrue()
        ->and($provider->calls)->toBe(0);
});

test('compiled operation validation does not retain prior operation values', function () {
    $compiled = Validator::compile([
        'operation' => 'required|string',
    ]);

    expect($compiled->validate(['operation' => 'exec'])->validated()['operation'])->toBe('exec')
        ->and($compiled->validate(['operation' => 'system'])->validated()['operation'])->toBe('system');
});

test('path validation checks syntax rather than filesystem trust or existence', function () {
    $result = Validator::make([
        'path' => 'required|path:relative',
    ])->validate([
        'path' => 'storage/uploads/nonexistent-report.pdf',
    ]);

    expect($result->passes())->toBeTrue();
});
