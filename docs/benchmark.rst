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

The executor skips empty cost phases and reuses compiled implicit-rule
metadata, filtering ``filled`` only for missing fields. These optimizations
preserve rule order, conditional placeholders and cancellation guards around
every executed callback. Compare the actual HTTP workload after executor
changes; faster component timings alone do not close the host RPM gate.

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

Release Checks
--------------

Before releasing a new revision:

* Verify clean production-only installs both without optional packages and
  with the supported DBLayer/Runwire chain, including a live advisory audit.
* Run PHPForge processing, detailed checks and the release guard with all
  configured detectors and thresholds active. Check native PHP 8.4/8.5,
  stable/lowest dependencies and ``E_ALL``.
* Build Sphinx with warnings treated as errors and run the documented host
  and intermediary integration examples.
* Compare batched and direct SQL predicates on PostgreSQL and MySQL,
  including type coercion, collation, ignored IDs and binding limits.
* Verify matched HTTP throughput and a persistent host-owned Runwire soak.
  Hosted fixtures do not replace deployment-specific application checks,
  worker replacement or actual driver/resource policies.
* Confirm all hosted checks on the exact final revision after code,
  dependency and documentation changes. Retain the workflow artifacts;
  earlier certificates cannot certify subsequent changes.

The final 3.3 code and documentation candidate
``35ea2efa5440aa22021d64dcedb50a2afc5cbde7`` passed all six hosted workflows.
The `HTTP comparison
<https://github.com/infocyph/ReqShield/actions/runs/37887599338>`_ measured
stable median successful RPM changes of +0.53%, +0.49% and +0.94% versus
the exact 3.2 tag at concurrency 1, 4 and 8, within the unchanged 2%
regression and 5% sample-variation limits. It used native PHP-FPM 8.4.26,
OPcache and production-only authoritative autoloaders. The fixture covers
repeated passing/failing nested and wildcard validation with mutable and
compiled validators; it does not certify sanitizer, cast, distinct or SQL
workloads.

The `300-second Runwire/DBLayer soak
<https://github.com/infocyph/ReqShield/actions/runs/37887599317>`_ passed
28,450 request cycles and 1,498 cancellation checks with 10 MiB peak PHP
allocation and zero retained active tasks or database bindings. It covers
interleaved tenant validations and pre-cancelled requests. Callback/deadline
cancellation and cooperative yielding are covered separately by regressions.
Consult the current revision's GitHub checks for new acceptance evidence.
