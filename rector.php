<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $resolveSet = static function (string $name): ?string {
        $setList = \Rector\Set\ValueObject\SetList::class;
        if (!class_exists($setList)) {
            return null;
        }

        $constant = $setList . '::' . $name;

        if (!defined($constant)) {
            return null;
        }

        $value = constant($constant);

        return is_string($value) ? $value : null;
    };

    $deadCodeMode = filter_var(
        getenv('REQSHIELD_RECTOR_DEAD_CODE') ?: '',
        FILTER_VALIDATE_BOOL,
    );

    $rectorConfig->paths($deadCodeMode
        ? [__DIR__ . '/src', __DIR__ . '/tests']
        : [__DIR__ . '/src']);

    $sets = [];

    if ($deadCodeMode) {
        $deadCodeSet = $resolveSet('DEAD_CODE') ?? __DIR__ . '/vendor/rector/rector/config/set/dead-code.php';
        if (is_string($deadCodeSet) && $deadCodeSet !== '') {
            $sets[] = $deadCodeSet;
        }
    } else {
        $phpSetCandidates = PHP_VERSION_ID >= 80500
            ? ['PHP_85', 'PHP_84', 'PHP_83']
            : ['PHP_84', 'PHP_83'];

        foreach ($phpSetCandidates as $candidate) {
            $set = $resolveSet($candidate);
            if ($set === null) {
                continue;
            }

            $sets[] = $set;
            break;
        }
    }

    if ($sets !== []) {
        $rectorConfig->sets($sets);
    }
};
