<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Contracts\Rule as RuleContract;
use Infocyph\ReqShield\Exceptions\DatabaseValidationException;
use Infocyph\ReqShield\Exceptions\InvalidRuleParameterException;
use Infocyph\ReqShield\Rules\IntegerRule;
use Infocyph\ReqShield\Rules\StringRule;
use Infocyph\ReqShield\Validator;

final class ReqShieldMutableAuditRule implements RuleContract
{
    public bool $passes = true;

    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} failed the mutable audit rule.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->passes && array_key_exists($field, $data) && $data[$field] === $value;
    }
}

final class ReqShieldAuditDatabaseProvider implements DatabaseProvider
{
    /** @var list<int> */
    public array $ids = [];

    public function __construct(private readonly bool $returnUnknownId = false) {}

    public function batchExists(string $table, array $checks): array
    {
        return $this->response($table, $checks);
    }

    public function batchUnique(string $table, array $checks): array
    {
        return $this->response($table, $checks);
    }

    /** @param list<array<string,mixed>> $checks */
    private function response(string $table, array $checks): array
    {
        if ($table === '') {
            throw new InvalidArgumentException('Database table cannot be empty.');
        }

        $this->ids = array_values(array_filter(array_column($checks, 'id'), is_int(...)));

        return $this->returnUnknownId ? [999999] : [];
    }
}

test('pure schema cache identity is collision safe and order sensitive', function () {
    Validator::clearPlanCache();

    $first = Validator::make([
        'a' => 'required',
        'bc' => 'string',
    ])->setStopOnFirstError(true)->validate([]);
    $second = Validator::make([
        'ab' => 'required',
        'c' => 'string',
    ])->setStopOnFirstError(true)->validate([]);
    $reordered = Validator::make([
        'second' => 'required',
        'first' => 'required',
    ])->setStopOnFirstError(true)->validate([]);

    expect(array_key_first($first->errors()))->toBe('a')
        ->and(array_key_first($second->errors()))->toBe('ab')
        ->and(array_key_first($reordered->errors()))->toBe('second');
});

test('conditional presence and nullable rules execute in the implicit phase', function () {
    $requiredIf = Validator::make([
        'value' => 'required_if:status,active|string',
    ]);
    $presentIf = Validator::make([
        'value' => 'present_if:status,active|string',
    ]);

    expect($requiredIf->validate(['status' => 'inactive'])->passes())->toBeTrue()
        ->and($requiredIf->validate(['status' => 'active'])->fails())->toBeTrue()
        ->and($presentIf->validate(['status' => 'inactive'])->passes())->toBeTrue()
        ->and($presentIf->validate(['status' => 'active'])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'required|nullable|string'])
            ->validate(['value' => null])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'nullable|string'])
            ->validate(['value' => null])->passes())->toBeTrue();
});

test('missing filled accepted and declined rules distinguish absence from emptiness', function () {
    $missing = Validator::make(['value' => 'missing|string']);
    $filled = Validator::make(['value' => 'filled']);

    expect($missing->validate([])->passes())->toBeTrue()
        ->and($missing->validate(['value' => ''])->fails())->toBeTrue()
        ->and($filled->validate(['value' => []])->fails())->toBeTrue()
        ->and($filled->validate(['value' => ''])->fails())->toBeTrue()
        ->and($filled->validate(['value' => 0])->passes())->toBeTrue()
        ->and($filled->validate(['value' => false])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'accepted_if:mode,active'])
            ->validate(['mode' => 'active'])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'declined_if:mode,active'])
            ->validate(['mode' => 'active'])->fails())->toBeTrue();
});

test('nested and wildcard rules retain and bind dependency paths', function () {
    $nested = Validator::make([
        'profile.value' => 'required_if:profile.status,active|string',
        'profile.copy' => 'same:profile.source',
        'profile.end' => 'after:profile.start',
    ]);
    $wildcard = Validator::make([
        'groups.*.members.*.confirmation' => 'same:groups.*.members.*.value',
    ]);

    expect($nested->validate([
        'profile' => [
            'status' => 'inactive',
            'source' => 'same',
            'copy' => 'same',
            'start' => '2026-01-01',
            'end' => '2026-01-02',
        ],
    ])->passes())->toBeTrue()
        ->and($nested->validate(['profile' => ['status' => 'active']])->fails())->toBeTrue()
        ->and($wildcard->validate([
            'groups' => [[
                'members' => [
                    ['value' => 'a', 'confirmation' => 'a'],
                    ['value' => 'b', 'confirmation' => 'wrong'],
                ],
            ]],
        ])->errors())->toHaveKey('groups.0.members.1.confirmation');
});

test('strict nested mode permits known containers and rejects or strips only unknown leaves', function () {
    $data = [
        'contacts' => [
            ['email' => 'valid@example.com', 'extra' => 'remove'],
        ],
    ];
    $schema = ['contacts.*.email' => 'required|email'];

    $strict = Validator::make($schema)->strict()->validate($data);
    $stripped = Validator::make($schema)->stripUnknown()->validate($data);

    expect($strict->errors())->toHaveKey('contacts.0.extra')
        ->and($strict->errors())->not->toHaveKey('contacts')
        ->and($strict->errors())->not->toHaveKey('contacts.0')
        ->and($stripped->passes())->toBeTrue()
        ->and($stripped->validated())->not->toHaveKey('contacts.0.extra');
});

test('textual parameters retain text semantics', function () {
    $validator = Validator::make([
        'choice' => 'in:1,2',
        'conditional' => 'required_if:status,1|string',
        'prefix' => 'starts_with:1',
    ]);

    expect($validator->validate([
        'choice' => '1',
        'status' => '1',
        'conditional' => 'yes',
        'prefix' => '123',
    ])->passes())->toBeTrue()
        ->and($validator->validate(['choice' => 1, 'status' => 0, 'prefix' => '123'])->fails())->toBeTrue();
});

test('IP option combinations are validated during compilation', function () {
    expect(Validator::make(['ip' => 'ip:v4,public'])->validate(['ip' => '8.8.8.8'])->passes())->toBeTrue()
        ->and(Validator::make(['ip' => 'ip:v4,private'])->validate(['ip' => '10.0.0.1'])->passes())->toBeTrue()
        ->and(Validator::make(['ip' => 'ip:v6'])->validate(['ip' => '2001:4860:4860::8888'])->passes())->toBeTrue();

    foreach (['ip:unknown', 'ip:v4,v6', 'ip:public,private'] as $rule) {
        expect(fn() => Validator::make(['ip' => $rule]))
            ->toThrow(InvalidRuleParameterException::class);
    }
});

test('paired regex delimiters and escaped colons compile correctly', function () {
    foreach ([
        '/foo|bar/' => 'bar',
        '/foo\\:bar/' => 'foo:bar',
        '{foo|bar}' => 'bar',
        '(foo|bar)' => 'bar',
        '[foo|bar]' => 'bar',
        '<foo|bar>' => 'bar',
    ] as $pattern => $value) {
        expect(Validator::make(['value' => 'regex:' . $pattern])
            ->validate(['value' => $value])->passes())->toBeTrue();
    }

    expect(fn() => Validator::make(['value' => 'regex:/[broken/']))
        ->toThrow(InvalidRuleParameterException::class);
});

test('digits decimal and wildcard distinct use their documented exact semantics', function () {
    $digits = Validator::make(['value' => 'digits:3']);

    foreach ([123, '123', '001'] as $value) {
        expect($digits->validate(['value' => $value])->passes())->toBeTrue();
    }
    foreach ([-123, 1.2, '1e3'] as $value) {
        expect($digits->validate(['value' => $value])->fails())->toBeTrue();
    }

    expect(Validator::make(['value' => 'decimal:2'])->validate(['value' => '1.20'])->passes())->toBeTrue()
        ->and(Validator::make(['value' => 'decimal:2'])->validate(['value' => '1.2'])->fails())->toBeTrue()
        ->and(Validator::make(['value' => 'decimal:0,4'])->validate(['value' => '1'])->passes())->toBeTrue()
        ->and(Validator::make(['items.*.code' => 'distinct'])->validate([
            'items' => [['code' => 'a'], ['code' => 'a']],
        ])->fails())->toBeTrue();

    foreach (['decimal:4,2', 'decimal:-1'] as $rule) {
        expect(fn() => Validator::make(['value' => $rule]))
            ->toThrow(InvalidRuleParameterException::class);
    }
});

test('database checks use distinct IDs and reject unknown provider IDs', function () {
    $provider = new ReqShieldAuditDatabaseProvider();
    Validator::make([
        'team_id' => ['exists:teams,id', 'exists:teams,id'],
    ], $provider)->validate(['team_id' => 1]);

    expect($provider->ids)->toHaveCount(2)
        ->and($provider->ids[0])->not->toBe($provider->ids[1]);

    $invalidProvider = new ReqShieldAuditDatabaseProvider(true);

    expect(fn() => Validator::make(['team_id' => 'exists:teams,id'], $invalidProvider)
        ->validate(['team_id' => 1]))->toThrow(DatabaseValidationException::class);
});

test('custom rule plans and caller rule objects are isolated', function () {
    $stringValidator = Validator::make(['value' => 'custom'], null, [
        'custom' => StringRule::class,
    ]);
    $integerValidator = Validator::make(['value' => 'custom'], null, [
        'custom' => IntegerRule::class,
    ]);
    $rule = new ReqShieldMutableAuditRule();
    $mutableValidator = Validator::make(['value' => [$rule]]);
    $rule->passes = false;

    expect($stringValidator->validate(['value' => 'text'])->passes())->toBeTrue()
        ->and($integerValidator->validate(['value' => 'text'])->fails())->toBeTrue()
        ->and($mutableValidator->validate(['value' => 'anything'])->passes())->toBeTrue();
});

test('JSON Schema export uses conservative ReqShield semantic extensions', function () {
    $schema = Validator::make([
        'site' => 'active_url',
        'team_id' => 'exists:teams,id',
        'date' => 'date_format:Y-m-d',
        'copy' => 'same:source',
        'conditional' => 'required_if:status,active',
    ], new ReqShieldAuditDatabaseProvider())->exportSchema('json_schema');

    expect($schema['properties']['site']['x-reqshield-active-url'])->toBeTrue()
        ->and($schema['properties']['team_id']['x-reqshield-exists'])->toBe([
            'table' => 'teams',
            'column' => 'id',
        ])
        ->and($schema['properties']['date']['x-reqshield-date-format'])->toBe('Y-m-d')
        ->and($schema['properties']['date'])->not->toHaveKey('format')
        ->and($schema['properties']['copy']['x-reqshield-same'])->toBe('source')
        ->and($schema['required'])->not->toContain('conditional');
});
