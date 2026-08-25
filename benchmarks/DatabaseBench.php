<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Benchmarks;

use Infocyph\DBLayer\DB;
use Infocyph\ReqShield\Rule;
use Infocyph\ReqShield\Tests\Integration\Database\DBLayerDatabaseProvider;
use Infocyph\ReqShield\Validator;
use PhpBench\Attributes as Bench;

#[Bench\Revs(50)]
#[Bench\Iterations(5)]
final class DatabaseBench
{
    private Validator $constrainedValidator;

    private Validator $existsValidator;

    private int $safeBatchSize;

    private Validator $uniqueValidator;

    public function __construct()
    {
        DB::resetRuntimeState();
        $connection = DB::addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'benchmark');
        $connection->statement('CREATE TABLE teams (id INTEGER PRIMARY KEY, code TEXT)');
        $connection->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $connection->insert('INSERT INTO teams (id, code) VALUES (?, ?)', [1, 'core']);
        $connection->insert('INSERT INTO users (id, email) VALUES (?, ?)', [1, 'existing@example.com']);
        $this->safeBatchSize = $connection->safeBatchSize(requested: 1_000);

        $provider = new DBLayerDatabaseProvider($connection);
        $this->existsValidator = Validator::make([
            'contacts.*.team_id' => 'required|exists:teams,id',
        ], $provider);
        $this->uniqueValidator = Validator::make([
            'contacts.*.email' => 'required|email|unique:users,email',
        ], $provider);

        $constrained = DB::addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'security' => ['max_params' => 32],
        ], 'benchmark-constrained');
        $constrained->statement('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $constrained->insert('INSERT INTO users (id, email) VALUES (?, ?)', [1, 'existing@example.com']);
        $this->constrainedValidator = Validator::make([
            'contacts.*.email' => Rule::unique('users', 'email')->ignore(1),
        ], new DBLayerDatabaseProvider($constrained));
    }

    #[Bench\Groups(['database', 'dblayer-sqlite-constrained-bind-limit'])]
    public function benchConstrainedDatabaseBatch(): void
    {
        $result = $this->constrainedValidator->validate([
            'contacts' => $this->uniqueContacts(1_000),
        ]);
        if ($result->fails()) {
            throw new \RuntimeException('Constrained benchmark produced an invalid result.');
        }
    }

    #[Bench\Groups(['database', 'dblayer-sqlite-batch'])]
    #[Bench\ParamProviders(['provideBatchSizes'])]
    public function benchDatabaseBatch(array $params): void
    {
        $result = $this->existsValidator->validate([
            'contacts' => array_fill(0, $params['size'], ['team_id' => 1]),
        ]);
        if ($result->fails()) {
            throw new \RuntimeException('Exists benchmark produced an invalid result.');
        }
    }

    #[Bench\Groups(['database', 'dblayer-sqlite-unique-batch'])]
    #[Bench\ParamProviders(['provideBatchSizes'])]
    public function benchDatabaseUniqueBatch(array $params): void
    {
        $result = $this->uniqueValidator->validate([
            'contacts' => $this->uniqueContacts($params['size']),
        ]);
        if ($result->fails()) {
            throw new \RuntimeException('Unique benchmark produced an invalid result.');
        }
    }

    /** @return iterable<string,array{size:int}> */
    public function provideBatchSizes(): iterable
    {
        $sizes = array_unique([
            1,
            10,
            100,
            $this->safeBatchSize - 1,
            $this->safeBatchSize,
            $this->safeBatchSize + 1,
            1_000,
        ]);
        foreach ($sizes as $size) {
            yield (string) $size => ['size' => $size];
        }
    }

    /** @return list<array{email:string}> */
    private function uniqueContacts(int $size): array
    {
        $contacts = [];
        foreach (range(1, $size) as $index) {
            $contacts[] = ['email' => "new-{$index}@example.com"];
        }

        return $contacts;
    }
}
