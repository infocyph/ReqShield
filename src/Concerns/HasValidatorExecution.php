<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Support\RunwireExecution;
use Infocyph\ReqShield\Support\ValidationResult;

trait HasValidatorExecution
{
    /**
     * @param array{
     *   errors:array<string,array<int,string>>,
     *   validated:array<string,mixed>,
     *   failures:array<int,array{field:string,rule:string,message:string,value:mixed}>,
     *   expensiveBatch:array<int,mixed>
     * } $context
     */
    protected function finalizeValidatedProjection(array &$context, \Infocyph\ReqShield\Support\ValidationPlan $plan): void
    {
        if (!$plan->hasExplicitAncestorFields && $this->afterCallbacks === [] && $context['errors'] === []) {
            return;
        }

        if (!$plan->hasExplicitAncestorFields && $this->afterCallbacks === []) {
            foreach (array_keys($context['errors']) as $field) {
                unset($context['validated'][(string) $field]);
            }

            return;
        }

        $this->purgeValidatedDescendants($context['validated'], $plan->fields, $context['errors']);
    }

    /** @param array<int|string,mixed> $data */
    protected function validateInternal(array $data, ?RunwireExecution $execution): ValidationResult
    {
        $execution?->checkpoint();
        $this->assertInputWithinLimits($data);
        $originalData = $data;
        [$data, $plan, $effectiveInput] = $this->prepareValidationDataAndSchema($data, $execution);
        $context = $this->initializeValidationContext();
        $context['execution'] = $execution;
        $execution?->checkpoint();
        $this->processUnknownFields($originalData, $data, $plan, $context, $effectiveInput);

        if (!empty($context['errors']) && $this->stopOnFirstError) {
            $result = $this->buildValidationResult($context);
            $execution?->checkpoint();
            $this->throwIfValidationShouldFail($result, $context['errors']);

            return $result;
        }

        $index = 0;
        foreach ($plan->fields as $field) {
            if ($execution !== null && (++$index & 255) === 0) {
                $execution->checkpoint(true);
            }
            $fieldPlan = $plan->schema[$field];
            $value = array_key_exists($field, $data) ? $data[$field] : null;
            if (!$this->processFieldValidation($field, $value, $fieldPlan, $data, $context)
                && $this->stopOnFirstError) {
                break;
            }
        }
        $execution?->checkpoint();
        $this->executeBatchedRules($context, $execution);
        $execution?->checkpoint();
        $this->executeAfterValidationCallbacks($data, $context);
        $execution?->checkpoint();
        $this->finalizeValidatedProjection($context, $plan);
        $result = $this->buildValidationResult($context);
        $execution?->checkpoint();
        $this->throwIfValidationShouldFail($result, $context['errors']);

        return $result;
    }
}
