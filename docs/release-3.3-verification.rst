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
* E: QA, security analysis, release guard, database engines and 300-second persistent host soak passed on ``bcb00a4f``. See workflows ``37873607084``, ``37873606200``, ``37873606051``, ``37873605970``, and ``37873606070``. A bounded correct-HTTP FPM comparison against exact 3.2 tag was **rejected** on run ``37873606074``: median successful RPM declines by 2.97%, 2.63%, and 2.20% at concurrency 1, 4, and 8 respectively, beyond the 2% budget. No release certificate is issued; production-host equivalence and final head SHA verification remain open.

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
4. **Cross-engine parity partly complete:** independent batched candidate parity passed
   on PostgreSQL 18, MySQL 8.4 and MySQL 9.7 on PHP 8.4/8.5 in run
   ``37873605970``. Restricted SQL policies, constrained parameter counts,
   and application-specific collations are not certified by this probe.
5. Repeated median successful host RPM against the 3.2 reference with
   representative passing and failing flat/nested/wildcard work,
   sanitizers/casts, distinct, compiled reuse, and indexed SQL queries.
   Capture matching host/DB/runtime versions, bounded concurrency,
   p50/p95/p99 latency, failures/timeouts, CPU, entire process-tree RSS,
   query/binding counts and database pool occupancy. Cold and warm cache
   series must be measured separately. Investigate sustained regressions
   above the PHPForge 2% acceptance budget.
6. **Runwire persistent-worker soak verified:** 300 seconds, 28,774 request
   cycles, 1,515 cancellation checks, stable bounded PHP allocation, no
   retained scopes or DBLayer bindings (run ``37873606070``). Actual
   application worker replacement and framework integration still need
   deployment-side validation.
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
