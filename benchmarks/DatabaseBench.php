<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Tests\Integration\Database\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(50)]
#[Bench\Iterations(5)]
final class DatabaseBench
{
    private Validator $validator;

    public function __construct()
    {
        DB::resetRuntimeState();
        $connection = DB::addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'benchmark');
        $connection->statement('CREATE TABLE teams (id INTEGER PRIMARY KEY, code TEXT)');
        $connection->insert('INSERT INTO teams (id, code) VALUES (?, ?)', [1, 'core']);

        $provider = new DBLayerDatabaseProvider($connection);
        $this->validator = Validator::make([
            'contacts.*.team_id' => 'required|exists:teams,id',
        ], $provider);
    }

    #[Bench\Groups(['database', 'dblayer-sqlite-batch'])]
    #[Bench\ParamProviders(['provideBatchSizes'])]
    public function benchDatabaseBatch(array $params): void
    {
        $this->validator->validate([
            'contacts' => array_fill(0, $params['size'], ['team_id' => 1]),
        ]);
    }

    /** @return iterable<string,array{size:int}> */
    public function provideBatchSizes(): iterable
    {
        foreach ([1, 10, 100, 401, 1000] as $size) {
            yield (string) $size => ['size' => $size];
        }
    }
}
