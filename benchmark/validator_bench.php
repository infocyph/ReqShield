<?php

declare(strict_types=1);

use Infocyph\ReqShield\Database\MockDatabaseProvider;
use Infocyph\ReqShield\Validator;

require __DIR__ . '/../vendor/autoload.php';

function progressPercent(int $completed, int $total, bool $forceNewline = false): void
{
    static $isTty = null;
    static $lastWidth = 0;
    static $lastPercent = -1;

    $total = max(1, $total);
    $percent = (int) floor(($completed / $total) * 100);
    $percent = max(0, min(100, $percent));

    if ($isTty === null) {
        $isTty = function_exists('stream_isatty') ? stream_isatty(STDOUT) : false;
    }
    if ($percent === $lastPercent) {
        if ($forceNewline && $isTty && $lastWidth > 0) {
            echo PHP_EOL;
            $lastWidth = 0;
        }
        return;
    }

    $text = '[progress] ' . $percent . '%';

    if ($isTty) {
        $len = strlen($text);
        $pad = $lastWidth > $len ? str_repeat(' ', $lastWidth - $len) : '';
        echo "\r" . $text . $pad;
        $lastWidth = max($lastWidth, $len);
        if ($forceNewline) {
            echo PHP_EOL;
            $lastWidth = 0;
        }
    } else {
        echo $text . PHP_EOL;
    }

    $lastPercent = $percent;

    if (function_exists('ob_get_level') && ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

/**
 * @param array<int,float> $samples
 */
function percentile(array $samples, float $percentile): float
{
    if ($samples === []) {
        return 0.0;
    }

    sort($samples);
    $index = (int) floor((count($samples) - 1) * $percentile);

    return $samples[$index] ?? 0.0;
}

/**
 * @return array{
 *   scenario:string,
 *   iterations:int,
 *   throughput:float,
 *   p50:float,
 *   p95:float,
 *   peakMb:float
 * }
 */
function runScenario(
    string $name,
    callable $buildValidator,
    array $payload,
    int $iterations = 3000,
    int $warmup = 300,
    ?callable $advance = null,
): array {
    $validator = $buildValidator();

    $warmReported = 0;
    $warmTick = max(1, intdiv(max(1, $warmup), 10));
    for ($i = 1; $i <= $warmup; $i++) {
        $validator->validate($payload);
        if ($advance !== null && ($i % $warmTick === 0 || $i === $warmup)) {
            $advance($i - $warmReported);
            $warmReported = $i;
        }
    }

    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $samples = [];
    $start = hrtime(true);
    $runReported = 0;
    $runTick = max(1, intdiv(max(1, $iterations), 20)); // 5%

    for ($i = 1; $i <= $iterations; $i++) {
        $lap = hrtime(true);
        $validator->validate($payload);
        $samples[] = (hrtime(true) - $lap) / 1_000_000.0;

        if ($advance !== null && ($i % $runTick === 0 || $i === $iterations)) {
            $advance($i - $runReported);
            $runReported = $i;
        }
    }

    $elapsedNs = hrtime(true) - $start;
    $throughput = $elapsedNs > 0
        ? ($iterations / ($elapsedNs / 1_000_000_000.0))
        : 0.0;

    return [
        'scenario' => $name,
        'iterations' => $iterations,
        'throughput' => $throughput,
        'p50' => percentile($samples, 0.50),
        'p95' => percentile($samples, 0.95),
        'peakMb' => memory_get_peak_usage(true) / 1_048_576.0,
    ];
}

$flatPayload = [
    'email' => 'bench@example.com',
    'username' => 'bench_user',
    'age' => 31,
    'status' => 'active',
    'country' => 'US',
    'zipcode' => '90210',
    'score' => 88,
    'newsletter' => 'yes',
    'profile' => '{"ok":true}',
];

$nestedPayload = [
    'users' => [
        [
            'email' => 'a@example.com',
            'age' => 22,
            'tags' => ['alpha', 'beta'],
        ],
        [
            'email' => 'b@example.com',
            'age' => 25,
            'tags' => ['gamma'],
        ],
        [
            'email' => 'c@example.com',
            'age' => 29,
            'tags' => ['delta', 'epsilon', 'zeta'],
        ],
    ],
];

$dbProvider = new MockDatabaseProvider();
$dbProvider->addData('users', [
    ['id' => 1, 'email' => 'existing@example.com', 'username' => 'existing_user'],
    ['id' => 2, 'email' => 'used@example.com', 'username' => 'used_user'],
]);
$dbProvider->addData('teams', [
    ['id' => 10, 'code' => 'ENG'],
    ['id' => 20, 'code' => 'OPS'],
]);

$dbPayload = [
    'email' => 'fresh@example.com',
    'username' => 'fresh_user',
    'backup_email' => 'fresh-backup@example.com',
    'team_id' => 10,
    'team_code' => 'ENG',
];

$results = [];

$totalUnits = (300 + 3000) + (300 + 3000) + (200 + 2000);
$completedUnits = 0;
$advance = static function (int $units) use (&$completedUnits, $totalUnits): void {
    $completedUnits += max(0, $units);
    progressPercent($completedUnits, $totalUnits);
};

progressPercent(0, $totalUnits);

$results[] = runScenario(
    'flat-fast-rules',
    static fn(): Validator => Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50|alpha_dash',
        'age' => 'required|integer|min:18|max:120',
        'status' => 'required|in:active,inactive,pending',
        'country' => 'required|string|size:2',
        'zipcode' => 'required|string|min:5|max:10',
        'score' => 'required|integer|min:0|max:100',
        'newsletter' => 'accepted',
        'profile' => 'json',
    ]),
    $flatPayload,
    3000,
    300,
    $advance,
);

$results[] = runScenario(
    'nested-wildcard',
    static fn(): Validator => Validator::make([
        'users.*.email' => 'required|email',
        'users.*.age' => 'required|integer|min:18',
        'users.*.tags.*' => 'required|string|min:2|max:20',
    ])->enableNestedValidation(),
    $nestedPayload,
    3000,
    300,
    $advance,
);

$results[] = runScenario(
    'db-heavy-batched',
    static fn(): Validator => Validator::make([
        'email' => 'required|email|unique:users,email',
        'username' => 'required|alpha_dash|unique:users,username',
        'backup_email' => 'required|email|unique:users,email',
        'team_id' => 'required|exists:teams,id',
        'team_code' => 'required|exists:teams,code',
    ], $dbProvider),
    $dbPayload,
    2000,
    200,
    $advance,
);

progressPercent($totalUnits, $totalUnits, true);

echo PHP_EOL . 'ReqShield Validator Benchmark' . PHP_EOL;
echo str_repeat('=', 92) . PHP_EOL;
echo str_pad('Scenario', 24)
    . str_pad('Iter', 10)
    . str_pad('Throughput (ops/s)', 22)
    . str_pad('P50 (ms)', 14)
    . str_pad('P95 (ms)', 14)
    . str_pad('Peak MB', 8)
    . PHP_EOL;
echo str_repeat('-', 92) . PHP_EOL;

foreach ($results as $row) {
    echo str_pad($row['scenario'], 24)
        . str_pad((string) $row['iterations'], 10)
        . str_pad(number_format($row['throughput'], 2), 22)
        . str_pad(number_format($row['p50'], 4), 14)
        . str_pad(number_format($row['p95'], 4), 14)
        . str_pad(number_format($row['peakMb'], 2), 8)
        . PHP_EOL;
}

echo str_repeat('=', 92) . PHP_EOL;
