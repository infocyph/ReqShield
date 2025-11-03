Database Rules (unique, exists)
===============================

ReqShield provides high-performance, batched validation for database rules like ``unique`` and ``exists``.

To use these rules, you must:
1.  Implement the ``DatabaseProvider`` interface.
2.  Pass your implementation to the ``Validator::make()`` method.

1. The ``DatabaseProvider`` Interface
------------------------------------

You need to create a class in your application that implements ``Infocyph\ReqShield\Contracts\DatabaseProvider``. This interface acts as a bridge between ReqShield and your database (e.g., PDO, Eloquent, Doctrine).

.. code-block:: php

    <?php
    namespace MyApp\Database;

    use Infocyph\ReqShield\Contracts\DatabaseProvider;
    use PDO;

    class MyDbProvider implements DatabaseProvider
    {
        protected $pdo;

        public function __construct(PDO $pdo) {
            $this->pdo = $pdo;
        }

        // This is a simplified example.
        // ReqShield's BatchExecutor will optimize this.
        public function exists(string $table, string $column, $value, ?int $ignoreId = null): bool {
            $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
            $params = [$value];

            if ($ignoreId !== null) {
                $sql .= " AND id != ?";
                $params[] = $ignoreId;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        }

        // ... you must also implement batchExistsCheck, batchUniqueCheck, etc.
        // For a full example, see the source of:
        // Infocyph\ReqShield\Database\MockDatabaseProvider

        // ... (rest of interface methods)
    }

(Implementation of the batch methods is complex. ReqShield is designed to batch queries for you. See ``src/Executors/BatchExecutor.php`` for how it builds queries.)

2. Using the Provider
---------------------

Once you have your provider, pass it as the second argument to ``Validator::make()``:

.. code-block:: php

    use Infocyph\ReqShield\Validator;

    // 1. Get your database connection (e.g., PDO)
    $pdo = new PDO(...);
    $dbProvider = new MyApp\Database\MyDbProvider($pdo);

    // 2. Pass the provider to the validator
    $validator = Validator::make(
        [
            'email' => 'required|email|unique:users,email',
            'category_id' => 'required|integer|exists:categories,id',
        ],
        $dbProvider // <-- Pass your provider here
    );

    // 3. Validate as usual
    $data = [
        'email' => 'new-user@example.com',
        'category_id' => 99 // Does category 99 exist?
    ];
    $result = $validator->validate($data);

    if ($result->fails()) {
        // Errors might include:
        // - "The email has already been taken."
        // - "The selected category_id is invalid."
    }

Rule Definitions
~~~~~~~~~~~~~~~~

* ``unique:table,column``
    * Checks if the value is unique in the ``table``'s ``column``.
    * **Example**: ``unique:users,email``

* ``exists:table,column``
    * Checks if the value already exists in the ``table``'s ``column``.
    * **Example**: ``exists:categories,id``
