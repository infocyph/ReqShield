<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\DatabaseBatchRule;
use Infocyph\ReqShield\Contracts\Rule;

final class FieldPlanBuilder
{
    private bool $bail = false;

    /** @var list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> */
    private array $batch = [];

    /** @var list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> */
    private array $cheap = [];

    /** @var list<string> */
    private array $dependencies = [];

    /** @var list<array{name:string,rule:Rule}> */
    private array $exclude = [];

    /** @var list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> */
    private array $expensive = [];

    private bool $filled = false;

    /** @var list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> */
    private array $implicit = [];

    /** @var list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> */
    private array $medium = [];

    /** @var list<string> */
    private array $names = [];

    private bool $nullable = false;

    private bool $optional = true;

    private bool $validateWhenMissing = false;

    /**
     * @param array<string,mixed> $placeholders
     * @param list<string> $dependencies
     */
    public function add(Rule $rule, string $name, array $placeholders, array $dependencies = []): void
    {
        $this->names[] = $name;
        $this->dependencies = array_values(array_unique([...$this->dependencies, ...$dependencies]));

        if ($name === 'required') {
            $this->optional = false;
        }

        if ($name === 'bail') {
            $this->bail = true;

            return;
        }

        if ($name === 'nullable') {
            $this->nullable = true;

            return;
        }

        if ($name === 'filled') {
            $this->filled = true;
        }

        if (str_starts_with($name, 'exclude')) {
            $this->exclude[] = ['name' => $name, 'rule' => $rule];

            return;
        }

        if ($this->isMissingValueRule($name)) {
            $this->validateWhenMissing = true;
            $this->implicit[] = ['name' => $name, 'placeholders' => $placeholders, 'rule' => $rule];

            return;
        }

        $compiled = ['name' => $name, 'placeholders' => $placeholders, 'rule' => $rule];
        if ($rule instanceof DatabaseBatchRule) {
            $this->batch[] = $compiled;
        } elseif ($rule->cost() < 50) {
            $this->cheap[] = $compiled;
        } elseif ($rule->cost() < 100) {
            $this->medium[] = $compiled;
        } else {
            $this->expensive[] = $compiled;
        }
    }

    public function build(): FieldPlan
    {
        $cheap = $this->sorted($this->cheap);
        $medium = $this->sorted($this->medium);
        $expensive = $this->sorted($this->expensive);
        $batch = $this->batch;

        return new FieldPlan(
            array_column($this->implicit, 'rule'),
            array_column($this->implicit, 'name'),
            array_column($this->implicit, 'placeholders'),
            array_column($cheap, 'rule'),
            array_column($cheap, 'name'),
            array_column($cheap, 'placeholders'),
            array_column($medium, 'rule'),
            array_column($medium, 'name'),
            array_column($medium, 'placeholders'),
            array_column($expensive, 'rule'),
            array_column($expensive, 'name'),
            array_column($expensive, 'placeholders'),
            $this->batchRules($batch),
            array_column($batch, 'name'),
            array_column($batch, 'placeholders'),
            array_column($this->exclude, 'rule'),
            array_column($this->exclude, 'name'),
            $this->names,
            $this->dependencies,
            $this->bail,
            $this->exclude !== [],
            $this->filled,
            $this->optional,
            $this->nullable,
            $this->validateWhenMissing,
        );
    }

    /**
     * @param list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> $rules
     * @return list<DatabaseBatchRule>
     */
    private function batchRules(array $rules): array
    {
        $batch = [];
        foreach ($rules as $compiled) {
            if ($compiled['rule'] instanceof DatabaseBatchRule) {
                $batch[] = $compiled['rule'];
            }
        }

        return $batch;
    }

    private function isMissingValueRule(string $name): bool
    {
        return $name === 'filled'
            || $name === 'accepted_if'
            || $name === 'declined_if'
            || str_starts_with($name, 'required')
            || str_starts_with($name, 'present')
            || str_starts_with($name, 'missing')
            || str_starts_with($name, 'prohibited')
            || $name === 'prohibits';
    }

    /**
     * @param list<array{name:string,placeholders:array<string,mixed>,rule:Rule}> $rules
     * @return list<array{name:string,placeholders:array<string,mixed>,rule:Rule}>
     */
    private function sorted(array $rules): array
    {
        usort(
            $rules,
            static fn(array $left, array $right): int => $left['rule']->cost() <=> $right['rule']->cost(),
        );

        return $rules;
    }
}
