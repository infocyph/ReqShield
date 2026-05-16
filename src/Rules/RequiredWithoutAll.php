<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithoutAll Rule - Cost: 2
 */
class RequiredWithoutAll extends AbstractRequiredRelatedFieldsRule
{
    public function message(string $field): string
    {
        return "The {$field} is required when none of {$this->joinedFields()} are present.";
    }

    protected function isRequiredFromRelatedFields(array $data): bool
    {
        return !array_any(
            $this->fields,
            fn(string $otherField): bool => $this->hasNonEmptyField($otherField, $data),
        );
    }
}
