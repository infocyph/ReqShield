<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Closure;
use Infocyph\ReqShield\Enums\BuiltinRule;
use Infocyph\ReqShield\Support\SchemaCompiler;
use PhpBench\Attributes as Bench;

#[Bench\Revs(2000)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class ResolverBench
{
    private SchemaCompiler $compiler;

    /** @var Closure(SchemaCompiler, string): string */
    private Closure $resolveRuleClass;

    public function __construct()
    {
        SchemaCompiler::clearResolvedBuiltinRuleClassCache();
        $this->compiler = new SchemaCompiler();
        $this->compiler->compile([
            'email' => 'required|email|max:255',
        ]);

        $this->resolveRuleClass = Closure::bind(
            static fn(SchemaCompiler $compiler, string $ruleName): string => $compiler->resolveRuleClass($ruleName),
            null,
            SchemaCompiler::class,
        );
    }

    #[Bench\Groups(['resolver', 'resolver-enum-resolve'])]
    public function benchBuiltinRuleResolve(): void
    {
        BuiltinRule::resolve('email');
    }

    #[Bench\Groups(['resolver', 'resolver-compiler-cached'])]
    public function benchCompilerCachedResolveRuleClass(): void
    {
        ($this->resolveRuleClass)($this->compiler, 'email');
    }

    #[Bench\Groups(['resolver', 'resolver-token-map-lookup'])]
    public function benchTokenToClassMapLookup(): void
    {
        BuiltinRule::tokenToClassMap()['email'];
    }
}
