<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Coroutine\TaskLocal;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\RuntimeCapability;
use Infocyph\Runwire\RuntimeContext;

/**
 * One validation call's host-owned cancellation and fairness checkpoint.
 * Neither the reusable validator nor a process-global cache retains this object.
 */
final readonly class RunwireExecution
{
    private ?TaskLocal $scopeProbe;

    public function __construct(
        public RuntimeContext $runtime,
        public ?RequestContext $request = null,
        public ?CoroutineScope $scope = null,
    ) {
        $this->scopeProbe = $scope === null ? null : new TaskLocal();
        $this->checkpoint();
    }

    /** @param list<callable(mixed):mixed> $pipeline */
    public function applyPipeline(mixed $value, array $pipeline): mixed
    {
        foreach ($pipeline as $callback) {
            $value = $this->invoke(static fn(): mixed => $callback($value));
        }

        return $value;
    }

    public function checkpoint(bool $yield = false): void
    {
        $pid = getmypid();
        if (!is_int($pid) || $this->runtime->pid !== $pid) {
            throw new \LogicException('Runwire runtime PID does not match the current process.');
        }

        if ($this->request !== null) {
            if ($this->request->completed() || $this->request->runtime() !== $this->runtime) {
                throw new \LogicException('Runwire request is completed or bound to another runtime.');
            }

            $this->request->cancellation->throwIfCancelled();
        }

        if ($this->scope !== null && $this->scopeProbe !== null) {
            $this->scope->cancellation()->throwIfCancelled();
            // An existing public, non-mutating scope guard validates scope openness
            // and that the call is running in the host scheduler's current task.
            $this->scope->hasLocal($this->scopeProbe);
        }

        if ($yield && $this->scope !== null && $this->runtime->supports(RuntimeCapability::RUNWIRE_COROUTINES)) {
            $this->scope->yieldNow();
            $this->checkpoint();
        }
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function invoke(callable $callback): mixed
    {
        $this->checkpoint();

        try {
            return $callback();
        } finally {
            $this->checkpoint();
        }
    }
}
