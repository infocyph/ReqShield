<?php

declare(strict_types=1);

use Infocyph\DBLayer\Connection\Connection;

test('native DBLayer bridge targets the released 6.0 API', function () {
    expect(method_exists(Connection::class, 'withRunwire'))->toBeTrue();
});

test('composer keeps native integrations optional and leaves ArrayKit constraints to DBLayer', function () {
    $composerFile = dirname(__DIR__, 3) . '/composer.json';
    $source = file_get_contents($composerFile);

    if ($source === false) {
        throw new RuntimeException('Unable to read the root Composer manifest.');
    }

    $manifest = json_decode($source, true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['require-dev']['infocyph/dblayer'])->toBe('^6.1.3')
        ->and($manifest['require-dev']['infocyph/runwire'])->toBe('^3.0')
        ->and($manifest['conflict']['infocyph/dblayer'])->toBe('<6.0')
        ->and($manifest['conflict'])->not->toHaveKey('infocyph/arraykit')
        ->and($manifest['require'])->not->toHaveKey('infocyph/dblayer')
        ->and($manifest['require'])->not->toHaveKey('infocyph/runwire')
        ->and($manifest['require'])->not->toHaveKey('infocyph/arraykit');
});
