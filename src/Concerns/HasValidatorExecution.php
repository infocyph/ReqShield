<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\RunwireExecution;
use Infocyph\ReqShield\Support\ValidationResult;

trait HasValidatorExecution
{
    /** @param array<int|string,mixed> $data */
    protected function validateInternal(array $data, ?RunwireExecution $execution): ValidationResult
    {
        $execution?->checkpoint();
        $this->assertInputWithinLimits($data);
        NestedValidator::assertNoConflictingPaths($data);
        $originalData = $data;
        [$data, $plan] = $this->prepareValidationDataAndSchema($data);
        $context = $this->initializeValidationContext();
        $this->processUnknownFields($originalData, $data, $plan, $context);

        if (!empty($context['errors']) && $this->stopOnFirstError) {
            $result = $this->buildValidationResult($context);
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
        $this->purgeValidatedDescendants($context['validated'], $plan->fields, $context['errors']);
        $result = $this->buildValidationResult($context);
        $this->throwIfValidationShouldFail($result, $context['errors']);

        return $result;
    }
}
