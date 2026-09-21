Database Rules (``unique``, ``exists``)
=======================================

Database rules are executed only through a caller-supplied
``Infocyph\ReqShield\Contracts\DatabaseProvider``. A schema containing a
database rule throws ``DatabaseProviderRequiredException`` during construction
when no provider is supplied; a database outage throws
``DatabaseValidationException`` and is never converted into a validation error.

Provider Contract
-----------------

The provider boundary intentionally contains only two operations:

.. code-block:: php

    interface DatabaseProvider
    {
        public function batchExists(string $table, array $checks): array;
        public function batchUnique(string $table, array $checks): array;
    }

Each check contains an ``id`` correlation token, ``field``, ``column``, and ``value``.
Unique checks additionally contain ``ignore``, ``id_column``,
``include_trashed``, and ``soft_delete_column``. The returned list contains the
IDs of failed checks. Providers own SQL generation, identifier allowlisting,
parameter binding, and physical query chunking; ReqShield sends one logical
batch and never exposes a generic query API. Every check ID is a distinct integer;
providers must return only IDs from the submitted batch. Unknown or malformed IDs
throw ``DatabaseValidationException``.

Using Database Rules
--------------------

.. code-block:: php

    use Infocyph\ReqShield\Rule;
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'team_id' => Rule::exists('teams', 'id'),
        'email' => [
            'required',
            'email',
            Rule::unique('users', 'email')
                ->ignore($userId)
                ->withoutTrashed(),
        ],
    ], $databaseProvider);

Simple string syntax remains available:

.. code-block:: php

    'team_id' => 'required|exists:teams,id'
    'email' => 'required|email|unique:users,email'

Use object syntax for ignore IDs, custom ID columns, or soft-delete behavior.
Complex positional ``unique`` options are intentionally not part of the 3.0 API.
Unique checks include all rows by default and therefore make no assumption that a
``deleted_at`` column exists. Call ``withoutTrashed()`` (optionally with a custom
column name) to opt into soft-delete filtering; ``withTrashed()`` restores the
default.

Native DBLayer 5.1 Integration
------------------------------

ReqShield remains database-library agnostic. DBLayer is an optional suggested
dependency, not a normal runtime requirement. When DBLayer 5.1 is installed,
ReqShield provides the native bridge
``Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider``.

Persistent/framework runtimes should prefer a resolver so the bridge receives
the current execution-owned connection without retaining it:

.. code-block:: php

    use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;

    $provider = new DBLayerDatabaseProvider(
        static fn () => $databaseFactory->connection(),
    );

For a caller-owned connection with a known safe lifetime, the convenience
factory is available:

.. code-block:: php

    $provider = DBLayerDatabaseProvider::fromConnection($connection);

The resolver is invoked exactly once at the beginning of each
``batchExists()`` or ``batchUnique()`` operation. The returned connection is
used for every physical chunk in that operation and is not stored on the
provider afterwards.

ReqShield owns logical validation batching. The native bridge returns SQL
``EXISTS`` match flags for each distinct candidate, so correlation preserves the
database's collation and numeric comparison rules. It never re-matches returned
column values using PHP string equality. Qualified column names work without
relying on the driver's returned column labels.

Physical query sizing uses the exact DBLayer connection's
``Connection::safeBatchSize()``. Each non-NULL candidate consumes one binding.
A unique-ignore predicate contributes one fixed binding for the whole correlated
candidate projection rather than repeating that binding per candidate. DBLayer
remains authoritative for bind limits. Repeated candidates are deduplicated by
both type and value.

The bridge also bounds query width to 128 candidates by default. This limits
SQL construction and result-column allocation independently of the driver's
bind ceiling. Applications may configure a positive ``maxBatchValues`` on the
constructor or ``fromConnection()``; larger values must fit the deployment's
SQL/result-column limits. ReqShield's normal input/wildcard limits remain the
higher-level validation abuse controls.

Unique-ignore handling preserves SQL NULL semantics with the logical predicate
``(id_column != :ignore OR id_column IS NULL)``. Candidate NULL values are
queried separately. Candidate values and ignore IDs are always bound parameters;
DBLayer constructs and quotes all table/column identifiers.

When DBLayer's ``raw_sql_policy`` is ``deny`` or ``allowlist``, the bridge uses
one query-builder-only lookup per distinct candidate instead of the batched
``CASE WHEN EXISTS`` projection. This preserves the connection's security policy
and SQL comparison behavior at the cost of additional round trips. It still
resolves the connection only once per provider operation.

The deterministic SQLite integration matrix covers flat, nested, wildcard,
mixed, duplicate and zero-like values, NULLs, ignore IDs, nullable/custom ID
columns, soft deletes, DBLayer-derived batch boundaries, constrained bind
limits, multi-chunk operations, resolver lifetime, identifier rejection and
infrastructure failures. Production bridge code uses DBLayer's instance
``Connection``/query-builder APIs only; it does not use the static DB facade.

