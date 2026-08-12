<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(200)]
#[Bench\Iterations(5)]
final class WildcardBench
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = Validator::make([
            'groups.*.members.*.email' => 'required|email',
        ])->limits(
            maxFields: 50_000,
            maxWildcardExpansions: 50_000,
            maxFlattenedPaths: 50_000,
        );
    }

    #[Bench\Groups(['wildcard', 'multi-wildcard'])]
    #[Bench\ParamProviders(['provideExpansionSizes'])]
    public function benchMultipleWildcardExpansion(array $params): void
    {
        $this->validator->validate([
            'groups' => [[
                'members' => array_fill(0, $params['size'], ['email' => 'bench@example.com']),
            ]],
        ]);
    }

    /** @return iterable<string,array{size:int}> */
    public function provideExpansionSizes(): iterable
    {
        foreach ([10, 100, 1000, 10000] as $size) {
            yield (string) $size => ['size' => $size];
        }
    }
}
