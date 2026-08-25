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

Reference Integration
---------------------

ReqShield is database-library agnostic. Applications may implement
``DatabaseProvider`` with PDO, DBLayer, Laravel, Doctrine, or another database
layer. DBLayer 5 is the development suite's reference integration, not a runtime
dependency for normal consumers.

ReqShield owns logical validation batching. The reference provider uses DBLayer
5's driver-aware ``Connection::safeBatchSize()`` for physical query chunks,
including fixed bindings introduced by unique-ignore predicates and a 1,000-value
application ceiling. This honors driver and configured ``security.max_params``
limits without duplicating bind-limit maps in ReqShield.

The deterministic SQLite integration matrix covers flat, nested, wildcard,
mixed, duplicate and zero-like values, ignore IDs, custom ID and soft-delete
columns, derived batch boundaries, constrained bind limits, runtime resets, and
infrastructure failures. It intentionally uses DBLayer's low-level connection
and query-builder APIs with plain array results; repositories, repository casts,
collections, relations, query caching, and DBLayer types are not part of
ReqShield's validation engine or public provider contract.
