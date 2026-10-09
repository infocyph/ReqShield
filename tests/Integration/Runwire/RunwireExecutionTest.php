<?php

declare(strict_types=1);

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Rules\Callback;
use Infocyph\ReqShield\Validator;
use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
use Infocyph\Runwire\Runtime\Enum\RuntimeDriver;
use Infocyph\Runwire\Runtime\RequestExecutionPolicy;
use Infocyph\Runwire\RuntimeCapabilities;
use Infocyph\Runwire\RuntimeContext;

test('passed host runtime validates without altering the ordinary API', function () {
    $runtime = RuntimeContext::standalone();
    $validator = Validator::make(['value' => 'required|integer']);

    expect($validator->validateWithRunwire(['value' => 12], $runtime)->passes())->toBeTrue()
        ->and($validator->validate(['value' => 12])->passes())->toBeTrue()
        ->and($validator->validateWithRunwire(['value' => 'wrong'], $runtime)->fails())->toBeTrue();
});

test('compiled validators accept passed host runtime without preserving request state', function () {
    $compiled = Validator::compile(['value' => 'required|integer']);
    $runtime = RuntimeContext::standalone();

    expect($compiled->validateWithRunwire(['value' => 5], $runtime)->passes())->toBeTrue()
        ->and($compiled->validate(['value' => 7])->passes())->toBeTrue();
});

test('runtime pid mismatch fails before validation starts', function () {
    $runtime = RuntimeContext::standalone();
    $wrong = RuntimeContext::fromCapabilities(
        $runtime->capabilities,
        $runtime->mode,
        pid: $runtime->pid + 1,
    );

    expect(fn() => Validator::make(['value' => 'integer'])
        ->validateWithRunwire(['value' => 2], $wrong))
        ->toThrow(LogicException::class);
});

test('request cancellation propagates without being rewritten as validation errors', function () {
    $request = RequestContext::standalone();
    $runtime = $request->runtime();
    $request->cancel(CancellationReason::HOST_CANCELLED);

    expect(fn() => Validator::make(['value' => 'integer'])
        ->validateWithRunwire(['value' => 2], $runtime, $request))
        ->toThrow(CancelledException::class);
});

test('completed requests and request-runtime mismatch are refused', function () {
    $completed = RequestContext::standalone();
    $runtime = $completed->runtime();
    $completed->complete();

    expect(fn() => Validator::make(['value' => 'integer'])
        ->validateWithRunwire(['value' => 2], $runtime, $completed))
        ->toThrow(LogicException::class);

    $other = RequestContext::standalone();
    expect(fn() => Validator::make(['value' => 'integer'])
        ->validateWithRunwire(['value' => 2], $runtime, $other))
        ->toThrow(LogicException::class);
});

test('sequential compiled executions do not retain cancelled request state', function () {
    $compiled = Validator::compile(['data.*.id' => 'integer']);
    $cancelled = RequestContext::standalone();
    $cancelled->cancel(CancellationReason::HOST_CANCELLED);

    expect(fn() => $compiled->validateWithRunwire(
        ['data' => [['id' => 1]]], $cancelled->runtime(), $cancelled,
    ))->toThrow(CancelledException::class);

    $other = RequestContext::standalone();
    expect($compiled->validateWithRunwire(
        ['data' => [['id' => 2]]], $other->runtime(), $other,
    )->passes())->toBeTrue();
});


test('host owned scope remains usable and closed scope fails without mutation', function () {
    $host = new CoroutineRuntime();
    $runtime = RuntimeContext::standalone();
    $scopeAfterClose = null;

    $success = $host->run(function (CoroutineScope $scope) use ($runtime, &$scopeAfterClose): bool {
        $scopeAfterClose = $scope;

        return Validator::make(['value' => 'integer'])
            ->validateWithRunwire(['value' => 3], $runtime, scope: $scope)->passes();
    });

    expect($success)->toBeTrue();
    expect(fn() => Validator::make(['value' => 'integer'])
        ->validateWithRunwire(['value' => 3], $runtime, scope: $scopeAfterClose))
        ->toThrow(LogicException::class);
});

test('compiled validator does not share execution state between interleaved coroutines', function () {
    $host = new CoroutineRuntime();
    $runtime = RuntimeContext::standalone();
    $compiled = Validator::compile(['data.*.id' => 'required|integer']);

    $results = $host->run(function (CoroutineScope $scope) use ($compiled, $runtime): array {
        $left = $scope->spawn(function () use ($compiled, $runtime, $scope): bool {
            $scope->yieldNow();

            return $compiled->validateWithRunwire(
                ['data' => [['id' => 1]]], $runtime, scope: $scope,
            )->passes();
        });
        $right = $scope->spawn(function () use ($compiled, $runtime, $scope): bool {
            return $compiled->validateWithRunwire(
                ['data' => [['id' => 'bad']]], $runtime, scope: $scope,
            )->fails();
        });

        return [$left->await(), $right->await()];
    });

    expect($results)->toBe([true, true]);
});


test('DBLayer withRunwire forwards one logical batch and restores host binding', function () {
    DB::resetRuntimeState();

    try {
        $connection = DB::addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ], 'reqshield-runwire');
        $connection->statement('CREATE TABLE records (id INTEGER PRIMARY KEY, token TEXT)');
        $connection->insert('INSERT INTO records (id, token) VALUES (?, ?)', [1, 'one']);

        $request = RequestContext::standalone();
        $runtime = $request->runtime();
        $provider = DBLayerDatabaseProvider::fromConnection($connection);
        $validator = Validator::make(['items.*' => 'exists:records,token'], $provider);

        expect($connection->runwireBinding())->toBeNull();
        $result = $validator->validateWithRunwire(
            ['items' => ['one', 'two']], $runtime, $request,
        );

        expect($result->fails())->toBeTrue()
            ->and($result->errors())->toHaveKey('items.1')
            ->and($connection->runwireBinding())->toBeNull();
    } finally {
        DB::resetRuntimeState();
    }
});

test('cancellation inside a sanitizer prevents later sanitizer and rule callbacks', function () {
    $request = RequestContext::standalone();
    $events = [];
    $validator = Validator::make(['value' => new Callback(static function () use (&$events): bool {
        $events[] = 'rule';
        return true;
    })])->setSanitizers(['value' => [
        static function ($value) use ($request, &$events) {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return $value;
        },
        static function ($value) use (&$events) {
            $events[] = 'later sanitizer';
            return $value;
        },
    ]]);

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
});

test('cancellation inside a rule prevents the next rule on the same field', function () {
    $request = RequestContext::standalone();
    $events = [];
    $validator = Validator::make(['value' => [
        new Callback(static function () use ($request, &$events): bool {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return true;
        }),
        new Callback(static function () use (&$events): bool {
            $events[] = 'later rule';
            return true;
        }),
    ]]);

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
});

test('sparse cost phases retain cancellation for mutable and compiled validators', function (int $cost, bool $compiled) {
    $request = RequestContext::standalone();
    $events = [];
    $builder = Validator::make(['value' => [
        new Callback(static function () use ($request, &$events): bool {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return true;
        }, cost: $cost),
        new Callback(static function () use (&$events): bool {
            $events[] = 'later';
            return true;
        }, cost: $cost),
    ]]);
    $validator = $compiled ? new \Infocyph\ReqShield\CompiledValidator($builder) : $builder;

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
})->with(['cheap' => 5, 'medium' => 50, 'expensive' => 150])->with([false, true]);

test('cancellation inside an after callback prevents the next after callback', function () {
    $request = RequestContext::standalone();
    $events = [];
    $validator = Validator::make(['value' => 'integer'])
        ->after(static function () use ($request, &$events): void {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
        })->after(static function () use (&$events): void { $events[] = 'later after'; });

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
});

test('cancellation inside a cast never returns a successful result or runs later casts', function () {
    $request = RequestContext::standalone();
    $events = [];
    $validator = Validator::make(['value' => 'integer'])->setCasts(['value' => [
        static function ($value) use ($request, &$events) {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return $value;
        },
        static function ($value) use (&$events) {
            $events[] = 'later cast';
            return $value;
        },
    ]]);

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
});

test('native database resolver cancellation propagates the host exception and restores binding', function () {
    DB::resetRuntimeState();
    try {
        $connection = DB::addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'resolver-cancel');
        $connection->statement('CREATE TABLE tokens (code TEXT)');
        $request = RequestContext::standalone();
        $provider = new DBLayerDatabaseProvider(static function () use ($request, $connection) {
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return $connection;
        });
        $validator = Validator::make(['value' => 'exists:tokens,code'], $provider);

        expect(fn() => $validator->validateWithRunwire(['value' => 'one'], $request->runtime(), $request))
            ->toThrow(CancelledException::class);
        expect($connection->runwireBinding())->toBeNull();
    } finally {
        DB::resetRuntimeState();
    }
});

test('ordinary database resolver errors retain the sanitized database exception boundary', function () {
    $request = RequestContext::standalone();
    $failure = new RuntimeException('private provider details');
    $provider = new DBLayerDatabaseProvider(static function () use ($failure) { throw $failure; });
    $validator = Validator::make(['value' => 'exists:tokens,code'], $provider);

    try {
        $validator->validateWithRunwire(['value' => 'one'], $request->runtime(), $request);
        throw new RuntimeException('Expected the database boundary to throw.');
    } catch (DatabaseValidationException $exception) {
        expect($exception->getPrevious())->toBe($failure)
            ->and($exception->getMessage())->not->toContain('private provider details');
    }
});

test('cancellation during a database query remains cancellation and clears temporary binding', function () {
    DB::resetRuntimeState();
    try {
        $connection = DB::addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'query-cancel');
        $connection->statement('CREATE TABLE tokens (code TEXT)');
        $request = RequestContext::standalone();
        $validator = Validator::make(['value' => 'exists:tokens,code'], DBLayerDatabaseProvider::fromConnection($connection));
        $query = fn() => $validator->validateWithRunwire(['value' => 'one'], $request->runtime(), $request);

        expect(fn() => $connection->withQueryCancellation(static function () use ($request): bool {
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return true;
        }, $query))->toThrow(CancelledException::class);
        expect($connection->runwireBinding())->toBeNull();
    } finally {
        DB::resetRuntimeState();
    }
});

test('conditional callbacks stop immediately after host cancellation', function () {
    $request = RequestContext::standalone();
    $events = [];
    $validator = Validator::make(['value' => 'integer'])->when(true,
        static function () use ($request, &$events): array {
            $events[] = 'cancel';
            $request->cancel(CancellationReason::HOST_CANCELLED);
            return [];
        },
    )->when(true, static function () use (&$events): array { $events[] = 'later when'; return []; });

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $request->runtime(), $request))
        ->toThrow(CancelledException::class);
    expect($events)->toBe(['cancel']);
});

test('request deadline expiry during casting cannot return a passing result', function () {
    $runtime = RuntimeContext::standalone();
    $request = RequestContext::create($runtime, new RequestExecutionPolicy(maxExecutionSeconds: 1.0));
    $validator = Validator::make(['value' => 'integer'])->setCasts(['value' => static function ($value) use ($request) {
        while (!$request->deadline()->expired()) {
            usleep(1_000);
        }
        return $value;
    }]);

    expect(fn() => $validator->validateWithRunwire(['value' => 1], $runtime, $request))
        ->toThrow(CancelledException::class);
    expect($request->cancellation->reason())->toBe(CancellationReason::DEADLINE_EXCEEDED);
});

test('automatic coroutine checkpoint observes sibling cancellation only with the supplied capability', function (bool $capable) {
    $host = new CoroutineRuntime();
    $runtime = RuntimeContext::fromCapabilities(
        new RuntimeCapabilities(RuntimeDriver::NATIVE, supportsRunwireCoroutines: $capable), 'test-host',
    );
    $request = RequestContext::create($runtime);
    $compiled = Validator::compile(['items.*' => 'required|integer']);

    $result = $host->run(function (CoroutineScope $scope) use ($compiled, $runtime, $request): bool {
        $validation = $scope->spawn(static function () use ($compiled, $runtime, $request, $scope): bool {
            try {
                return $compiled->validateWithRunwire(['items' => range(1, 300)], $runtime, $request, $scope)->passes();
            } catch (CancelledException) {
                return false;
            }
        });
        $cancellation = $scope->spawn(static function () use ($request): void {
            $request->cancel(CancellationReason::HOST_CANCELLED);
        });
        $passed = $validation->await();
        $cancellation->await();
        return $passed;
    });

    expect($result)->toBe(!$capable);
    $next = RequestContext::create($runtime);
    expect($compiled->validateWithRunwire(['items' => [1]], $runtime, $next)->passes())->toBeTrue();
})->with([false, true]);

test('null-returning pipelines execute each callback exactly once', function (bool $bound) {
    $events = [];
    $validator = Validator::make(['value' => 'nullable|integer'])
        ->setSanitizers(['value' => static function () use (&$events): mixed {
            $events[] = 'sanitizer';
            return null;
        }])->when(true, static function () use (&$events): mixed {
            $events[] = 'when';
            return null;
        })->after(static function () use (&$events): void {
            $events[] = 'after';
        })->setCasts(['value' => static function () use (&$events): mixed {
            $events[] = 'cast';
            return null;
        }]);
    $result = $bound
        ? $validator->validateWithRunwire(['value' => 1], RuntimeContext::standalone())
        : $validator->validate(['value' => 1]);

    expect($result->passes())->toBeTrue()
        ->and($result->typed()['value'])->toBeNull()
        ->and($events)->toBe(['sanitizer', 'when', 'after', 'cast']);
})->with([false, true]);
