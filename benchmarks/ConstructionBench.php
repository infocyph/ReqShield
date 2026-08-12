<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\CompiledValidator;
use Infocyph\ReqShield\Rules\Callback;
use Infocyph\ReqShield\Rules\Required;
use Infocyph\ReqShield\Rules\StringRule;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(100)]
#[Bench\Iterations(5)]
#[Bench\Warmup(1)]
final class ConstructionBench
{
    private CompiledValidator $compiled;

    /** @var array<string,string> */
    private array $fiftyFields;

    /** @var array<string,string> */
    private array $oneField = ['field_0' => 'required|string|max:255'];

    /** @var array<string,string> */
    private array $oneHundredFields;

    private Validator $reused;

    /** @var array<string,string> */
    private array $tenFields;

    public function __construct()
    {
        $this->tenFields = $this->schema(10);
        $this->fiftyFields = $this->schema(50);
        $this->oneHundredFields = $this->schema(100);
        $this->compiled = Validator::compile($this->fiftyFields);
        $this->reused = Validator::make($this->fiftyFields);
    }

    #[Bench\Groups(['construction', 'compiled-reuse'])]
    public function benchCompiledReuse(): void
    {
        $this->compiled->validate(array_fill_keys(array_keys($this->fiftyFields), 'value'));
    }

    #[Bench\Groups(['construction', 'conditional-schema'])]
    public function benchConditionalSchema(): void
    {
        Validator::make(['value' => 'string'])
            ->sometimes('value', 'required', static fn(): bool => true);
    }

    #[Bench\Groups(['construction', 'custom-rule'])]
    public function benchCustomRule(): void
    {
        Validator::make(['value' => [new Callback(static fn(mixed $value): bool => $value !== null)]]);
    }

    #[Bench\Groups(['construction', 'fifty-fields'])]
    public function benchFiftyFields(): void
    {
        Validator::make($this->fiftyFields);
    }

    #[Bench\Groups(['construction', 'object-schema'])]
    public function benchObjectSchema(): void
    {
        Validator::make(['value' => [new Required(), new StringRule()]]);
    }

    #[Bench\Groups(['construction', 'one-field'])]
    public function benchOneField(): void
    {
        Validator::make($this->oneField);
    }

    #[Bench\Groups(['construction', 'one-hundred-fields'])]
    public function benchOneHundredFields(): void
    {
        Validator::make($this->oneHundredFields);
    }

    #[Bench\Groups(['construction', 'ten-fields'])]
    public function benchTenFields(): void
    {
        Validator::make($this->tenFields);
    }

    #[Bench\Groups(['construction', 'validator-reuse'])]
    public function benchValidatorReuse(): void
    {
        $this->reused->validate(array_fill_keys(array_keys($this->fiftyFields), 'value'));
    }

    /** @return array<string,string> */
    private function schema(int $fields): array
    {
        $schema = [];
        for ($index = 0; $index < $fields; ++$index) {
            $schema["field_{$index}"] = 'required|string|max:255';
        }

        return $schema;
    }
}
