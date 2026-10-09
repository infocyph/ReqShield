<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Infocyph\DBLayer\Connection\Connection;
use Infocyph\DBLayer\Connection\ConnectionConfig;
use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Validator;
use Infocyph\Runwire\Coroutine\CoroutineRuntime;
use Infocyph\Runwire\Coroutine\CoroutineScope;
use Infocyph\Runwire\Exception\CancelledException;
use Infocyph\Runwire\RequestContext;
use Infocyph\Runwire\Runtime\Enum\CancellationReason;

$seconds = (int) (getenv('REQSHIELD_SOAK_SECONDS') ?: '300');
if ($seconds < 300) {
    throw new InvalidArgumentException('Release soak requires at least 300 seconds.');
}

$connections = [];
$validators = [];

foreach (['tenant_a', 'tenant_b'] as $tenant) {
    $connection = new Connection(
        ConnectionConfig::fromArray(['driver' => 'sqlite', 'database' => ':memory:']),
        'reqshield_soak_' . $tenant,
    );
    $connection->statement('CREATE TABLE soak_tokens (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
    $connection->insert('INSERT INTO soak_tokens (id, code) VALUES (?, ?)', [1, $tenant]);

    $connections[$tenant] = $connection;
    $validators[$tenant] = Validator::compile([
        'user.id' => 'required|integer',
        'values.*.id' => 'required|integer',
        'token' => 'required|exists:soak_tokens,code',
    ], DBLayerDatabaseProvider::fromConnection($connection));
}

$host = new CoroutineRuntime();
$started = hrtime(true);
$end = $started + $seconds * 1_000_000_000;
$cycles = 0;
$cancelled = 0;
$baselineMemory = null;
$peakMemory = memory_get_usage(true);
$maxActiveTasks = 0;

try {
    while (hrtime(true) < $end) {
        $request = RequestContext::standalone();
        $runtime = $request->runtime();

        $outcome = $host->runRequest(
            $request,
            static function (CoroutineScope $scope) use ($validators, $runtime, $request): array {
                $first = $scope->spawn(static function () use ($scope, $validators, $runtime, $request): bool {
                    $scope->yieldNow();

                    return $validators['tenant_a']->validateWithRunwire([
                        'user' => ['id' => 10],
                        'values' => [['id' => 1], ['id' => 2]],
                        'token' => 'tenant_a',
                    ], $runtime, $request, $scope)->passes();
                });
                $second = $scope->spawn(static fn(): bool => $validators['tenant_b']->validateWithRunwire([
                    'user' => ['id' => 'bad'],
                    'values' => [['id' => 3]],
                    'token' => 'tenant_b',
                ], $runtime, $request, $scope)->fails());

                $results = [$first->await(), $second->await()];
                $scope->sleep(0.01);

                return $results;
            },
        );

        if ($outcome !== [true, true]) {
            throw new RuntimeException('Cross-tenant interleaved validation returned an incorrect result.');
        }

        $request->complete();
        if (!$request->completed()) {
            throw new RuntimeException('Host request did not complete.');
        }

        if ($cycles % 19 === 0) {
            $aborted = RequestContext::standalone();
            $aborted->cancel(CancellationReason::HOST_CANCELLED);
            $caught = false;

            try {
                $validators['tenant_a']->validateWithRunwire(
                    ['user' => ['id' => 1], 'token' => 'tenant_a'],
                    $aborted->runtime(),
                    $aborted,
                );
            } catch (CancelledException) {
                $caught = true;
            } finally {
                $aborted->complete();
            }

            if (!$caught) {
                throw new RuntimeException('Cancelled request did not propagate the Runwire exception.');
            }

            ++$cancelled;
        }

        foreach ($connections as $connection) {
            if ($connection->runwireBinding() !== null) {
                throw new RuntimeException('DBLayer retained a Runwire binding across requests.');
            }
        }

        $diagnostics = $host->diagnostics();
        if ($diagnostics->activeTasks !== 0 || $diagnostics->requestScopesActive !== 0) {
            throw new RuntimeException('Runwire retained tasks or request scopes across requests.');
        }

        $maxActiveTasks = max($maxActiveTasks, $diagnostics->activeTasks);
        $peakMemory = max($peakMemory, memory_get_usage(true));
        $elapsedSeconds = (hrtime(true) - $started) / 1_000_000_000;

        if ($elapsedSeconds >= 30 && $baselineMemory === null) {
            $baselineMemory = memory_get_usage(true);
        }

        if ($baselineMemory !== null && memory_get_usage(true) > $baselineMemory + 32 * 1024 * 1024) {
            throw new RuntimeException('Worker memory exceeds the permitted post-warmup 32 MiB growth.');
        }

        ++$cycles;

        if ($cycles % 1000 === 0) {
            fwrite(STDOUT, sprintf(
                "SOAK elapsed=%.1fs cycles=%d cancelled=%d peak_memory=%d\n",
                $elapsedSeconds,
                $cycles,
                $cancelled,
                $peakMemory,
            ));
        }
    }

    if ($cycles < 100 || $cancelled < 5) {
        throw new RuntimeException('Insufficient live request/cancellation cycles completed during the soak.');
    }

    fwrite(STDOUT, sprintf(
        "PASS RUNWIRE_SOAK seconds=%d requests=%d cancellations=%d peak_bytes=%d active_tasks=%d\n",
        $seconds,
        $cycles,
        $cancelled,
        $peakMemory,
        $maxActiveTasks,
    ));
} finally {
    foreach ($connections as $connection) {
        $connection->disconnect();
    }
}
