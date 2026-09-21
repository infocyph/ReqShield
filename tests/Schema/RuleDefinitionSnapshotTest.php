<?php

declare(strict_types=1);

use Infocyph\ReqShield\Exceptions\InvalidSchemaException;
use Infocyph\ReqShield\Support\RuleDefinitionSnapshot;
use Infocyph\ReqShield\Tests\Fixtures\Validation\NestedStateRule;

test('rule snapshots reject mutable descendants even when their parent is cloned', function () {
    $rule = new NestedStateRule();
    $rule->config->nested = ['child' => (object) ['allow' => true]];

    expect(fn() => RuleDefinitionSnapshot::rule($rule))->toThrow(InvalidSchemaException::class);
});

test('rule snapshots reject reference cells that would survive PHP cloning', function () {
    $rule = new NestedStateRule();
    $allow = true;
    $rule->config->allow = &$allow;

    expect(fn() => RuleDefinitionSnapshot::rule($rule))->toThrow(InvalidSchemaException::class);
});

test('rule snapshots reject cycles that still reach the original mutable graph', function () {
    $rule = new NestedStateRule();
    $rule->config->self = $rule->config;

    expect(fn() => RuleDefinitionSnapshot::rule($rule))->toThrow(InvalidSchemaException::class);
});

test('rule snapshots preserve documented callback ownership and immutable values', function () {
    $rule = new NestedStateRule();
    $rule->config->callback = static fn(): bool => true;
    $rule->config->date = new DateTimeImmutable('2026-01-01');
    $rule->config->enum = \Infocyph\ReqShield\Tests\Fixtures\ReqShieldStatus::cases()[0];
    $copy = RuleDefinitionSnapshot::rule($rule);

    expect($copy)->not->toBe($rule)
        ->and($copy->config)->not->toBe($rule->config)
        ->and($copy->config->callback)->toBe($rule->config->callback)
        ->and($copy->config->date)->toBe($rule->config->date)
        ->and($copy->config->enum)->toBe($rule->config->enum);
});

test('rule snapshots reject opaque internal containers instead of sharing hidden objects', function () {
    $rule = new NestedStateRule();
    $rule->config->storage = new SplObjectStorage();
    $rule->config->storage->offsetSet((object) ['allow' => true]);

    expect(fn() => RuleDefinitionSnapshot::rule($rule))->toThrow(InvalidSchemaException::class);
});
