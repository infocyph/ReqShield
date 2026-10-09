<?php

declare(strict_types=1);

use Infocyph\ReqShield\Validator;
use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;
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
