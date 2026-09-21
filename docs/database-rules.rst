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

ReqShield owns logical validation batching. The native bridge delegates physical
query sizing to the exact DBLayer connection through
``Connection::safeBatchSize()``, including fixed bindings introduced by
unique-ignore predicates. It does not duplicate DBLayer driver bind-limit maps
or impose the old reference provider's fixed 1,000-value ceiling. ReqShield's
normal input/wildcard limits remain the higher-level validation abuse controls.

Unique-ignore handling preserves SQL NULL semantics with the logical predicate
``(id_column != :ignore OR id_column IS NULL)``. Candidate NULL values are
queried separately, repeated values are deduplicated before ``WHERE IN``
generation, and zero-like scalar values retain database-comparison semantics.

The deterministic SQLite integration matrix covers flat, nested, wildcard,
mixed, duplicate and zero-like values, NULLs, ignore IDs, nullable/custom ID
columns, soft deletes, DBLayer-derived batch boundaries, constrained bind
limits, multi-chunk operations, resolver lifetime, identifier rejection and
infrastructure failures. Production bridge code uses DBLayer's instance
``Connection``/query-builder APIs only; it does not use the static DB facade.

