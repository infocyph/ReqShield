Development Commands
====================

PHPForge provides the Composer quality commands for local development and
CI-aligned checks. ReqShield defines the representative benchmark wrapper.

Core Commands
-------------

.. code-block:: bash

    composer ic:tests
    composer ic:tests:details
    composer ic:test:code
    composer ic:test:lint
    composer ic:test:sniff
    composer ic:test:static
    composer ic:test:security
    composer ic:test:duplicates
    composer ic:benchmark
    composer ic:bench:quick
    composer ic:process
    composer ic:release:guard

For issue resolution, start with ``composer ic:doctor``, ``ic:list-config``
and ``ic:active-config``. Then run ``ic:process``, review its generated changes,
run ``ic:tests:details``, and finish with ``ic:release:guard``. Fix findings
without weakening configured detectors or thresholds.

What They Run
-------------

* ``ic:tests``: full quality suite
* ``ic:tests:details``: expanded non-shortcut quality suite
* ``ic:test:code``: Pest test run
* ``ic:test:lint``: Pint check mode
* ``ic:test:sniff``: PHPCS
* ``ic:test:static``: PHPStan
* ``ic:test:security``: Psalm security analysis
* ``ic:test:duplicates``: duplicate code detection
* ``ic:benchmark`` / ``ic:bench:quick``: PhpBench benchmark suite
* ``ic:process``: Rector + Pint + PHPCBF processing pipeline
* ``ic:release:guard``: Composer validation, stable runtime constraints,
  advisory audit and the full quality suite

Git Hooks
---------

CaptainHook is wired through Composer:

.. code-block:: bash

    composer ic:hooks

Hooks are also installed automatically on ``post-autoload-dump``.

Database Reference Tests
------------------------

DBLayer 6.0 is installed only as a development dependency and backs the reference
``DatabaseProvider`` integration tests. Those tests exercise driver-aware
physical batching, constrained ``security.max_params`` configurations, unique
ignore bindings, soft deletes, scalar edge cases, wildcard batches, and DBLayer
runtime reset between tests. ReqShield's production API remains independent of
DBLayer.

Runwire is pinned to **2.1.1** for development integration tests and remains
optional for consumers. The Runwire regressions cover callback cancellation,
deadline expiry, resolver/query cancellation, temporary binding restoration,
automatic yielding with and without advertised coroutine support, and
exactly-once null-returning callbacks. Security regressions cover wildcard
rule delimiters and decoded unknown fields in both nested traversal modes.

Run the suite on native PHP 8.4 and 8.5 with ``E_ALL``, including slug behavior
under glibc/libiconv and optional Intl availability. Recheck production-only
validation without DBLayer or Runwire installed.

The reusable CI workflow invokes ``benchmark:representative`` as a Composer
script; it runs the same PHPForge aggregate benchmark task as
``composer ic:benchmark``.

The repository also supplies ``probes/db-cross-engine-parity.php``,
``probes/runwire-persistent-soak.php`` and ``probes/host-http/``. Their hosted
workflows exercise released dependencies, SQL engines, a 300-second host
lifecycle and correct PHP-FPM HTTP workloads. A certificate applies only to
its recorded commit and workload; see :doc:`benchmark` for acceptance
requirements and the scope of the hosted evidence.
