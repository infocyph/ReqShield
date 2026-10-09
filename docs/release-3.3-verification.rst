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
* E: hosted PHPForge/Sphinx and real MySQL/PostgreSQL SQL-parity suites passed at candidate `5e81576`; production-host RPM, persistent-worker soak, final release guard and tag decision are still open. The final PR SHA must be rechecked after any documentation updates.

Unverified Final Gates
----------------------

1. A clean, authoritative production-only Composer install without DBLayer
   or Runwire and a complete consumer with released DBLayer 6.0 and
   Runwire 2.1.1; verify package versions, reference commits and advisories.
2. Final-tree ``composer ic:process``, ``ic:tests:details`` and
   ``ic:release:guard``, with no skipped or weakened PHPForge detectors.
   Check PHP 8.4/8.5, stable/lowest dependencies and E_ALL.
3. Build the Sphinx documentation with warnings as errors; validate the
   Runwire host and intermediary code examples.
4. **Partially verified:** SQLite package tests and independent bound
   candidate parity tests passed on PostgreSQL 18.6 and MySQL 8.4.11 on
   PHP 8.4/8.5 (11 mixed candidates, exists and unique) in hosted run
   37867942309. Re-run representative MySQL 9.x, restricted SQL policy,
   parameter limits and application-specific collation workloads before
   final release acceptance.
5. Repeated median successful host RPM against the 3.2 reference with
   representative passing and failing flat/nested/wildcard work,
   sanitizers/casts, distinct, compiled reuse, and indexed SQL queries.
   Capture matching host/DB/runtime versions, bounded concurrency,
   p50/p95/p99 latency, failures/timeouts, CPU, entire process-tree RSS,
   query/binding counts and database pool occupancy. Cold and warm cache
   series must be measured separately. Investigate sustained regressions
   above the PHPForge 2% acceptance budget.
6. At least 300 seconds of a real host-owned persistent-worker soak with
   two interleaved requests, cancellation, tenant/worker switching,
   failures, fresh scopes and connection-binding cleanup. Require bounded
   memory/resource use and zero unexpected errors or retained bindings.
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
