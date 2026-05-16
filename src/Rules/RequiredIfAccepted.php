<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredIfAccepted Rule - Cost: 2
 */
class RequiredIfAccepted extends AbstractRequiredIfStateRule
{
    protected function triggerLabel(): string
    {
        return 'accepted';
    }

    /** @return list<string|int|bool> */
    protected function triggerValues(): array
    {
        return ['yes', 'on', '1', 1, true, 'true'];
    }
}
