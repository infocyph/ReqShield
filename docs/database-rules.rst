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

Testing Providers
-----------------

The development suite includes a DBLayer 4.0.0 provider and deterministic
SQLite fixtures. It verifies flat, nested, wildcard, mixed, ignore-ID,
custom-ID-column, soft-delete, batching, and infrastructure-error behavior.
DBLayer is a development dependency only; ReqShield stays driver agnostic.
