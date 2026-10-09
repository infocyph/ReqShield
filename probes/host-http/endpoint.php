<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Infocyph\ReqShield\Validator;

$source = file_get_contents('php://input');
if (!is_string($source) || $source === '') {
    http_response_code(400);

    throw new \RuntimeException('The HTTP benchmark request or validation failed.');
}

$payload = json_decode($source, true, 512, JSON_THROW_ON_ERROR);
if (!is_array($payload) || !isset($payload['seed']) || !is_int($payload['seed'])) {
    http_response_code(400);

    throw new \RuntimeException('The HTTP benchmark request or validation failed.');
}

$seed = $payload['seed'];
$rules = [
    'name' => 'required|string',
    'user.age' => 'required|integer',
    'items.*.score' => 'required|integer',
    'metadata.note' => 'required|string',
];
$valid = [
    'name' => 'valid',
    'user' => ['age' => $seed % 99],
    'items' => [
        ['score' => 1],
        ['score' => 2],
        ['score' => 3],
        ['score' => 4],
        ['score' => 5],
    ],
    'metadata' => ['note' => 'stable'],
];
$invalid = $valid;
$invalid['user']['age'] = 'not-an-integer';

$compiled = Validator::compile($rules);
$validator = Validator::make($rules);

for ($index = 0; $index < 6; ++$index) {
    if (!$compiled->validate($valid)->passes() || !$validator->validate($valid)->passes()) {
        http_response_code(500);

        throw new \RuntimeException('The HTTP benchmark request or validation failed.');
    }
    if (!$compiled->validate($invalid)->fails() || !$validator->validate($invalid)->fails()) {
        http_response_code(500);

        throw new \RuntimeException('The HTTP benchmark request or validation failed.');
    }
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
file_put_contents('php://output', 'ok');
