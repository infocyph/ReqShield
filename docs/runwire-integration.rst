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

Automatic Capability Use and Fallback
-------------------------------------

ReqShield uses the supplied request's cancellation and deadline state. Long
field loops yield cooperatively at bounded checkpoints only when the supplied
runtime advertises ``RUNWIRE_COROUTINES`` and a live scope is passed from the
host's current coroutine task. Without that capability or scope, validation
stays synchronous while preserving available cancellation checks. ReqShield
does not create a replacement runtime, scheduler, worker or scope.

When Runwire is not installed, or the caller has no active runtime, call
``validate()``. Intermediary libraries can accept an optional runtime and select
that ordinary path, as shown below. The same objects must be forwarded through
every intermediary; an unrelated standalone context does not share the host's
cancellation, deadline or task lifetime.

Cancellation checks surround each sanitizer, condition, rule, after-validation
callback and cast, and run before result delivery. Cancellation or deadline
expiry throws ``Infocyph\Runwire\Exception\CancelledException`` before the
next library-dispatched callback. A currently executing synchronous callback
must return or throw before that check can run; its completed side effects
cannot be undone. Callback return values, including ``null``, are evaluated
once.

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
            ?RuntimeContext $runtime = null,
            ?RequestContext $request = null,
            ?CoroutineScope $scope = null,
        ): bool {
            $result = $runtime === null
                ? $this->validator->validate($input)
                : $this->validator->validateWithRunwire(
                    $input, $runtime, $request, $scope,
                );

            return $result->passes();
        }
    }

    // The host supplies the current connection resolver and Runwire contexts.
    $service = new SubmissionValidation($databaseFactory);
    $accepted = $service->check(
        $input, $hostRuntime, $hostRequest, $hostScope,
    );

    // The same service also supports an ordinary caller without Runwire.
    $ordinaryAccepted = $service->check($input);

The bridge resolves one connection per logical provider operation. DBLayer
6.0's ``Connection::withRunwire()`` borrows that context for the operation,
including its physical chunks, and restores the prior binding on exit.
The host remains responsible for connection and worker lifetimes.
Host cancellation during resolution/query execution propagates ``CancelledException``,
including when DBLayer reports a query-cancellation exception. Other provider
errors retain ReqShield's sanitized ``DatabaseValidationException`` boundary.

Compatibility and Boundaries
----------------------------

* DBLayer **6.0** is the minimum supported native integration.
  DBLayer 5.x and ArrayKit versions below 5.3 are unsupported, blocked by
  Composer, and have no compatibility fallback.
* A mismatched PID, completed or mismatched request, closed scope and
  cancellation reject execution.
* Missing coroutine capability or scope uses synchronous validation. Passing
  context does not make PDO, image decoding or DNS operations asynchronous.
* The regular ``validate()`` path does not initialize a Runwire runtime.
* Do not retain an execution's request or coroutine scope across requests;
  the host owns cancellation, disposal, transport mapping and worker resets.

See :doc:`security-boundaries` and :doc:`upgrading-3.3`.
