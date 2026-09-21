<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\CompiledValidator;
use Infocyph\ReqShield\Schema\SchemaRegistry;
use Infocyph\ReqShield\Support\ValidatorProfile;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(100)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class RuntimeTopologyBench
{
    private CompiledValidator $compiled;

    private ValidatorProfile $profile;

    private SchemaRegistry $registry;

    /** @var array<string,string> */
    private array $rules;

    /** @var array<string,string> */
    private array $validData;

    public function __construct()
    {
        $this->rules = [
            'email' => 'required|email|max:255',
            'age' => 'required|integer|min:18',
            'name' => 'required|string|min:2|max:120',
        ];
        $this->validData = [
            'email' => 'ada@example.com',
            'age' => '30',
            'name' => 'Ada',
        ];
        $this->profile = ValidatorProfile::fromArray([
            'fail_fast' => false,
            'sanitizers' => ['email' => ['trim', 'lowercase']],
            'casts' => ['age' => 'integer'],
            'limits' => ['max_fields' => 100],
        ]);
        $this->registry = (new SchemaRegistry([
            'users.store' => $this->rules,
        ]))->freeze();
        $this->compiled = new CompiledValidator(
            $this->profile->apply(Validator::make($this->rules)),
        );
    }

    #[Bench\Groups(['runtime-topology', 'compiled-reuse'])]
    public function benchCompiledReuse(): void
    {
        $this->compiled->validate($this->validData);
    }

    #[Bench\Groups(['runtime-topology', 'profile-apply'])]
    public function benchProfileApply(): void
    {
        $this->profile->apply(Validator::make($this->rules));
    }

    #[Bench\Groups(['runtime-topology', 'raw-setters'])]
    public function benchRawSetterConfiguration(): void
    {
        Validator::make($this->rules)
            ->setFailFast(false)
            ->setSanitizers(['email' => ['trim', 'lowercase']])
            ->setCasts(['age' => 'integer'])
            ->limits(maxFields: 100);
    }

    #[Bench\Groups(['runtime-topology', 'registry-lookup'])]
    public function benchRegistryLookup(): void
    {
        $this->registry->get('users.store');
    }
}
