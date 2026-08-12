<?php

declare(strict_types=1);

use Infocyph\ReqShield\Validator;

test('upload guards reject unsafe names failed uploads and malformed metadata', function (array $upload) {
    $result = Validator::make([
        'upload' => 'required|secure_file',
    ])->validate(['upload' => $upload]);

    expect($result->errors())->toHaveKey('upload');
})->with([
    'path traversal' => [[
        'name' => '../payload.php',
        'type' => 'application/x-httpd-php',
        'size' => 10,
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
    ]],
    'control character' => [[
        'name' => "report\0.txt",
        'type' => 'text/plain',
        'size' => 10,
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
    ]],
    'failed status' => [[
        'name' => 'report.txt',
        'type' => 'text/plain',
        'size' => 0,
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_PARTIAL,
    ]],
    'malformed size' => [[
        'name' => 'report.txt',
        'type' => 'text/plain',
        'size' => -1,
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
    ]],
]);

test('strict MIME detection rejects an extension and content mismatch', function () {
    $path = tempnam(sys_get_temp_dir(), 'reqshield-security-');
    if ($path === false) {
        throw new RuntimeException('Unable to create upload fixture.');
    }

    file_put_contents($path, 'plain text content');

    try {
        $upload = [
            'name' => 'avatar.jpg',
            'type' => 'image/jpeg',
            'size' => filesize($path),
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
        ];

        expect(Validator::make([
            'upload' => 'required|mimes:jpg',
        ])->validate(['upload' => $upload])->fails())->toBeTrue();
    } finally {
        unlink($path);
    }
})->skip(!function_exists('finfo_open') && !function_exists('mime_content_type'));
