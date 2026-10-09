<?php

declare(strict_types=1);

use Infocyph\ReqShield\Support\RunwireExecution;
use Infocyph\ReqShield\Validator;
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
