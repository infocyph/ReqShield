Database Rules (``unique``, ``exists``)
=======================================

ReqShield batches expensive DB validation rules across fields for performance.
To use DB rules, provide a ``DatabaseProvider`` implementation to ``Validator::make($rules, $dbProvider)``.

For provider-native batch execution, also implement
``Infocyph\ReqShield\Contracts\NativeBatchDatabaseProvider`` (marker interface).
If you only implement ``DatabaseProvider``, ReqShield falls back to chunked ``query()``-based checks.

DatabaseProvider Implementation
-------------------------------

Your class must implement ``Infocyph\ReqShield\Contracts\DatabaseProvider``.

.. code-block:: php

    <?php
    namespace App\Validation;

    use Infocyph\ReqShield\Contracts\DatabaseProvider;
    use PDO;

    final class PdoDatabaseProvider implements DatabaseProvider
    {
        public function __construct(private PDO $pdo)
        {
        }

        public function batchExistsCheck(string $table, array $checks): array
        {
            return [];
        }

        public function batchUniqueCheck(string $table, array $checks): array
        {
            return [];
        }

        public function compositeUnique(
            string $table,
            array $columns,
            ?int $ignoreId = null,
        ): bool {
            return true;
        }

        public function exists(
            string $table,
            string $column,
            $value,
            ?int $ignoreId = null,
        ): bool {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $params = [$value];

            if ($ignoreId !== null) {
                $sql .= " AND `id` != ?";
                $params[] = $ignoreId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (int)$stmt->fetchColumn() > 0;
        }

        public function query(string $query, array $params = []): array
        {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

Use the Provider
----------------

.. code-block:: php

    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email|unique:users,email',
        'category_id' => 'required|integer|exists:categories,id',
    ], $dbProvider);

    $result = $validator->validate($payload);

Rule Syntax
-----------

``exists``:

* ``exists:table,column``
* Example: ``exists:users,id``

``unique``:

* ``unique:table,column``
* ``unique:table,column,ignoreId``
* ``unique:table,column,ignoreId,idColumn,withTrashed,softDeleteColumn``

Examples:

.. code-block:: php

    'email' => 'unique:users,email'
    'email' => 'unique:users,email,10' // ignore row id=10
    'email' => 'unique:users,email,,id,false,deleted_at' // ignore soft-deleted rows
    'email' => 'unique:users,email,,id,true,deleted_at'  // include soft-deleted rows

Object Rule for Full Control
----------------------------

Use ``Infocyph\ReqShield\Rules\Unique`` directly when you need explicit constructor parameters.

.. code-block:: php

    use Infocyph\ReqShield\Rules\Unique;

    $validator = Validator::make([
        'email' => [
            'required',
            new Unique(
                table: 'users',
                column: 'email',
                ignoreId: null,
                idColumn: 'id',
                withTrashed: false,
                softDeleteColumn: 'deleted_at',
            ),
        ],
    ], $dbProvider);
