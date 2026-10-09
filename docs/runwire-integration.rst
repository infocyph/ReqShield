Host-owned Runwire 2.1.1 Integration
=====================================

ReqShield 3.3 adds an optional passed-instance integration with Runwire 2.1.1.
The application owns the runtime, request and coroutine scope. ReqShield
neither starts an event loop nor retains host execution bindings.

.. code-block:: bash

    composer require 'infocyph/runwire:2.1.1'
    composer require 'infocyph/dblayer:^6.0'

The second package is only needed for the native database bridge; ordinary
validation needs neither package.

Passed-instance Validation
--------------------------

.. code-block:: php

    use Infocyph\ReqShield\Validator;

    $compiled = Validator::compile([
        'items.*.id' => 'required|integer',
    ]);

    // The host supplies these live objects: ReqShield never creates them.
    $result = $compiled->validateWithRunwire(
        $input,
        $hostRuntime,
        $hostRequest,
        $hostScope, // optional
    );

    // Ordinary usage does not require Runwire.
    $otherResult = $compiled->validate($input);

The same ``validateWithRunwire()`` signature is available on a normal
``Validator``. Host request and scope objects must remain valid throughout
the call. Cancellation and deadline errors propagate to the host as
Runwire exceptions; they are not ordinary field validation failures.

Execution-owned DBLayer Connection
----------------------------------

Pass a resolver that returns the connection owned by the current execution.
The intermediary forwards the *same* host-owned runtime objects:

.. code-block:: php

    use Infocyph\DBLayer\Connection\Connection;
    use Infocyph\ReqShield\Bridge\DBLayerDatabaseProvider;
    use Infocyph\ReqShield\Validator;
    use Infocyph\Runwire\Coroutine\CoroutineScope;
    use Infocyph\Runwire\RequestContext;
    use Infocyph\Runwire\RuntimeContext;

    final class SubmissionValidation
    {
        private Validator $validator;

        public function __construct(private object $databaseFactory)
        {
            $provider = new DBLayerDatabaseProvider(
                fn (): Connection => $this->databaseFactory->connection(),
            );

            $this->validator = Validator::make([
                'team' => 'required|exists:teams,id',
                'email' => 'required|email|unique:users,email',
            ], $provider);
        }

        public function check(
            array $input,
            RuntimeContext $runtime,
            ?RequestContext $request = null,
            ?CoroutineScope $scope = null,
        ): bool {
            return $this->validator
                ->validateWithRunwire($input, $runtime, $request, $scope)
                ->passes();
        }
    }

    // The host supplies the current connection resolver and Runwire contexts.
    $service = new SubmissionValidation($databaseFactory);
    $accepted = $service->check(
        $input, $hostRuntime, $hostRequest, $hostScope,
    );

The bridge resolves one connection per logical provider operation. DBLayer
6.0's ``Connection::withRunwire()`` borrows that context for the operation,
including its physical chunks, and restores the prior binding on exit.
The host remains responsible for connection and worker lifetimes.

Compatibility and Boundaries
----------------------------

* DBLayer **6.0** is the minimum supported native integration.
  DBLayer 5.x and ArrayKit versions below 5.3 are unsupported, blocked by
  Composer, and have no compatibility fallback.
* A mismatched PID, completed or mismatched request, closed scope and
  cancellation reject execution.
* Long validations perform bounded cooperative checkpoints. Runwire does not
  automatically make synchronous PDO, image decoding or DNS operations
  asynchronous.
* The regular ``validate()`` path does not initialize a Runwire runtime.
* Do not retain an execution's request or coroutine scope across requests;
  the host owns cancellation, disposal, transport mapping and worker resets.

See :doc:`security-boundaries` and :doc:`upgrading-3.3`.
