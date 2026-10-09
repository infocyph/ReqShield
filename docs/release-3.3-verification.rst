ReqShield 3.3 Release Verification
===================================

This is an *open acceptance record*, not a release certificate. Hosted
PHPForge quality and benchmark jobs are necessary but do not prove
application-level requests per minute (RPM) or persistent-worker safety.

Batch Evidence
--------------

* A: ``1c1e18e``, hosted run ``37862931807``, passed.
* B: ``790f8a9``, hosted run ``37863850902``, passed.
* C: ``9135b8d``, hosted run ``37864826678``, passed.
* D: ``8223ff9``, hosted run ``37866171652``, passed.
* E: Full package and host-equivalent CI gates **passed on code candidate** ``055b22b5``: PHPForge matrix ``37875840442``, release guard ``37875839892``, Sphinx ``37875839891``, MySQL 8.4/9.7 + PostgreSQL 18 parity ``37875839909``, persistent 300-second Runwire soak ``37875839938`` (28,293 request cycles, 1,490 cancellations, 10 MiB peak PHP allocation, zero retained active tasks), and correct-HTTP nginx/PHP-FPM parity versus the exact 3.2 tag ``37875839887``. Median successful RPM changes were **−1.88%, −1.80%, and −1.10%** for concurrency 1, 4, and 8: stable and within the unchanged 2% performance threshold. Deployment-host application behavior and the final PR revision after documentation changes must still be checked before the maintainer tags a release.

Hosted Gates Passed; Deployment Review Remaining
------------------------------------------------

1. A clean, authoritative production-only Composer install without DBLayer
   or Runwire and a complete consumer with released DBLayer 6.0 and
   Runwire 2.1.1; verify package versions, reference commits and advisories.
2. Final-tree ``composer ic:process``, ``ic:tests:details`` and
   ``ic:release:guard``, with no skipped or weakened PHPForge detectors.
   Check PHP 8.4/8.5, stable/lowest dependencies and E_ALL.
3. Build the Sphinx documentation with warnings as errors; validate the
   Runwire host and intermediary code examples.
4. **Cross-engine CI passed:** independent batched candidate parity passed on
   PostgreSQL 18, MySQL 8.4 and MySQL 9.7 on PHP 8.4/8.5 in run
   ``37875839909``. Deployment-specific restricted SQL policies, constrained
   parameter counts, and application collation requirements remain host review items.
5. Repeated median successful host RPM against the 3.2 reference with
   representative passing and failing flat/nested/wildcard work,
   sanitizers/casts, distinct, compiled reuse, and indexed SQL queries.
   Capture matching host/DB/runtime versions, bounded concurrency,
   p50/p95/p99 latency, failures/timeouts, CPU, entire process-tree RSS,
   query/binding counts and database pool occupancy. Cold and warm cache
   series must be measured separately. Investigate sustained regressions
   above the PHPForge 2% acceptance budget.
6. **Runwire persistent-worker soak verified:** 300 seconds, 28,293 request
   cycles, 1,490 cancellation checks, 10 MiB peak PHP allocation, no
   retained scopes or DBLayer bindings (run ``37875839938``). Application
   worker replacement and framework-specific lifecycle integration remain
   deployment-side review items.
7. Final CI and release guard on the exact PR head SHA after package and
   documentation updates. Record the SHA and all acceptance artifacts.
   Do not merge, tag or publish until remaining gates have passed.

The GitHub package benchmark jobs do **not** supply a complete real-FPM
or host-owned persistent runtime RPM/soak environment. No successful
microbenchmark should be reported as production throughput certification.

Published test dependency floor
-------------------------------

Composer now rejects ``infocyph/dblayer <6.0`` and
``infocyph/arraykit <5.3`` if those packages are present. The
native bridge directly uses DBLayer 6's Runwire API without fallback.
The QA resolver installed DBLayer 6.0, ArrayKit 5.3, CacheLayer 4.0 and
Runwire 2.1.1 on the passing candidate; database packages remain
optional for ordinary non-database validation.
