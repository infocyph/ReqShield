<?php

use Infocyph\ReqShield\Validator;

// --- Example 18 ---

test('performance benchmark completes', function () {
    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50|alpha_dash',
        'age' => 'required|integer|min:18',
        'bio' => 'string|max:1000',
    ]);

    $data = [
        'email' => 'perf@test.com',
        'username' => 'perfuser',
        'age' => 30,
        'bio' => 'Test bio',
    ];

    $iterations = 100; // Reduced for a quick test run
    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $validator->validate($data);
    }

    $duration = (microtime(true) - $start) * 1000;

    expect($duration)->toBeGreaterThanOrEqual(0);
    echo "\nPerformed {$iterations} validations in {$duration}ms\n";

})->group('performance'); // Mark as performance test to be run optionally
