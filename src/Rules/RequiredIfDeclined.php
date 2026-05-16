<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredIfDeclined Rule - Cost: 2
 */
class RequiredIfDeclined extends AbstractRequiredIfStateRule
{
    protected function triggerLabel(): string
    {
        return 'declined';
    }

    /** @return list<string|int|bool> */
    protected function triggerValues(): array
    {
        return ['no', 'off', '0', 0, false, 'false'];
    }
}
