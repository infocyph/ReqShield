Benchmarking
============

ReqShield uses PhpBench to keep construction, runtime, and memory costs visible.

.. code-block:: bash

    composer ic:benchmark
    composer ic:bench:quick

The suite covers:

* fresh construction at 1, 10, 50, and 100 fields;
* immutable compiled-plan reuse, string/object/custom/conditional schemas;
* frozen ``SchemaRegistry`` lookup, reusable ``ValidatorProfile`` application,
  direct setter configuration, and compiled snapshot reuse;
* flat passing and first/middle/final failure paths;
* fail-fast and collect-all behavior;
* active/inactive implicit rules and nullable/optional short circuits;
* optimized nested traversal and multiple wildcards;
* compiled sanitizer and cast pipelines;
* localized and wildcard failure messages;
* wildcard scaling through 10,000 matches;
* DBLayer 6.0 direct-query baseline, native provider resolver overhead, and
  SQLite exists/unique checks around connection-derived safe batch
  boundaries through 1,000 wildcard values;
* DBLayer 6.0 unique-ignore validation under a constrained 32-parameter ceiling;
* built-in rule resolution.

PhpBench reports timing variance and peak memory. Compare results only on a
stable environment. PHPForge's benchmark-result validation and comparison
commands provide the regression-budget gate used for release baselines.

Release RPM and Persistent-Worker Acceptance
--------------------------------------------

PhpBench and GitHub benchmark jobs are useful isolated runtime checks;
they do not certify successful host requests per minute (RPM).

For the 3.3 release compare matched, repeated successful median RPM
against 3.2 under identical flat/nested/wildcard, cast/sanitizer,
compiled, distinct and SQL workloads. Record cold/warm cache conditions,
bounded concurrency, p50/p95/p99 latency, errors, timeouts, CPU,
whole-process-tree RSS, query/binding counts and connection pool occupancy.
Apply PHPForge's default 2% regression budget only to equivalent steady
states, and do not count skipped validation as a successful request.

Also require a real host-owned Runwire persistent worker soak lasting
at least 300 seconds with cancellations, multiple request contexts,
worker replacement and verified zero retained database bindings.
Keep the acceptance record in :doc:`release-3.3-verification`.
