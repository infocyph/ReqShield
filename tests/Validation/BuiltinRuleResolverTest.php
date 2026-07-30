<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Enums\BuiltinRule;
use Infocyph\ReqShield\Support\SchemaCompiler;
use Infocyph\ReqShield\Tests\Fixtures\SchemaCompilerTestRule;

it('all builtin rules resolve to valid rule classes', function (): void {
    foreach (BuiltinRule::cases() as $rule) {
        $class = $rule->ruleClass();

        expect(class_exists($class))->toBeTrue($rule->value);
        expect(is_subclass_of($class, Rule::class))->toBeTrue($rule->value);
    }
});

it('resolves builtin rules both ways', function (): void {
    foreach (BuiltinRule::cases() as $rule) {
        $class = BuiltinRule::resolve($rule->value);

        expect($class)->toBe($rule->ruleClass());
        expect(BuiltinRule::resolveNameForClass($rule->ruleClass()))
            ->toBe($rule->value);
    }
});

it('caches resolved builtin rule classes across compiles', function (): void {
    SchemaCompiler::clearResolvedBuiltinRuleClassCache();
    $compiler = new SchemaCompiler();

    $compiler->compile([
        'email' => 'required|email|max:255',
    ]);

    $ref = new ReflectionClass(SchemaCompiler::class);
    /** @var array<string, string> $firstCache */
    $firstCache = $ref->getStaticPropertyValue('resolvedBuiltinRuleClassCache');

    $compiler->compile([
        'email' => 'required|email|max:255',
    ]);

    /** @var array<string, string> $secondCache */
    $secondCache = $ref->getStaticPropertyValue('resolvedBuiltinRuleClassCache');

    expect($firstCache)->toHaveKeys(['required', 'email', 'max']);
    expect($secondCache)->toBe($firstCache);
});

it('keeps custom rules outside builtin rule class cache', function (): void {
    SchemaCompiler::clearResolvedBuiltinRuleClassCache();

    $compiler = new SchemaCompiler();
    $compiler->registerRule('custom', SchemaCompilerTestRule::class);

    $compiler->compile([
        'number' => 'required|custom',
    ]);

    $cache = (new ReflectionClass(SchemaCompiler::class))
        ->getStaticPropertyValue('resolvedBuiltinRuleClassCache');

    expect($cache)->toHaveKey('required');
    expect($cache)->not->toHaveKey('custom');
});
