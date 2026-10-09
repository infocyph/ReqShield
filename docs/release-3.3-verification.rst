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

Follow-up Review and Repairs (2026-10-09)
-----------------------------------------

All six hosted workflows also passed at ``86a2bc7``. Independent composition
probes nevertheless reproduced four further defects: wildcard captures
altering rule grammar, decoded unknown fields surviving strict/strip
policies, callbacks/result delivery continuing after cancellation, and
native database cancellation being wrapped as a generic database failure.

The follow-up repairs reject unrepresentable wildcard captures, inspect
sanitized input before flattening, guard each execution-owned callback and
final result, and recheck host cancellation on database exception paths.
Regressions include mutable/compiled validators, both flattening modes,
deadline expiry, cancellation during a query, and automatic coroutine
yielding with and without the host's advertised capability.

Native PHP 8.5 testing additionally reproduced libiconv accent punctuation
creating extra slug separators. The repair removes only accent markers
generated from letters and preserves the caller's own punctuation.

Local follow-up validation passes: 375 tests and 4,006 assertions on native
PHP 8.4/8.5, PHPForge ``ic:process``, ``ic:tests:details`` and
``ic:release:guard``, and production-only fallback without DBLayer or Runwire.
The live audit reports zero advisories; abandoned ``doctrine/annotations``
is a non-blocking development dependency of the benchmark tooling.

Documentation builds with Sphinx warnings treated as errors. All 197 PHP
snippets in the README and Sphinx guides pass syntax checks in their stated
schema/class context; the host/intermediary and decoded-unknown examples also
run successfully. Stale DBLayer development guidance, sanitizer counts,
enum class syntax and missing schema separators were corrected.

The repaired working tree also passes the 300-second local persistent soak:
27,103 request cycles, 1,427 cancellation checks, 10 MiB peak PHP allocation,
48.79 MiB peak process-tree RSS, 8 KiB post-warmup RSS growth, and zero retained
active tasks or database bindings. This fixture covers small interleaved
tenant validations and pre-cancelled requests. Callback/deadline cancellation
and automatic yielding are covered separately by the regression suite;
application worker replacement remains a deployment acceptance item.

The final local HTTP comparison **did not pass** the unchanged 2% RPM / 5%
sampling-variance gate. It compared the repaired working tree based on
``86a2bc7`` with the exact ``3.2`` tag using production-only authoritative
autoloaders, nginx and native PHP-FPM 8.5.11 with OPcache and no Xdebug.
Both paths ran on the same eight-core PHP-FPM pool, with the load generator
on separate physical cores. Three paired 10-second windows per concurrency
followed warmup; all measured responses passed the fixture's correctness and
transport checks.

.. list-table:: Final local HTTP result
   :header-rows: 1

   * - Concurrency
     - Baseline median RPM
     - Candidate median RPM
     - Change
     - Acceptance
   * - 1
     - 60,442.8
     - 55,443.6
     - -8.27%
     - Failed 2% regression budget
   * - 4
     - 182,847.6
     - 191,074.8
     - +4.50%
     - Candidate variance 8.30%; unstable
   * - 8
     - 287,604.0
     - 294,116.4
     - +2.26%
     - Passed this workload

Peak live PHP-FPM process-tree RSS was 131.84 MiB. Earlier local series also
showed scheduling variation and budget failures. A focused callback-loop
simplification preserves the cancellation guards; it does not close this
HTTP gate. The fixture measures repeated passing/failing nested and wildcard
validation with mutable/compiled validators, rather than sanitizer, cast,
distinct or SQL workloads. The release remains open pending performance
resolution and fresh hosted gates on the exact repaired final commit.

Hosted Throughput Follow-up (2026-10-09)
----------------------------------------

The subsequent ``77be83fa18ddf5f810f7f242dc0c60572f106f5b`` revision passed
the hosted QA, release guard, documentation, SQL parity and persistent-soak
workflows. The `HTTP throughput job
<https://github.com/infocyph/ReqShield/actions/runs/37883154305>`_ failed
with stable median RPM changes of -2.84%, -2.89% and -2.87% at concurrency
1, 4 and 8 against the exact 3.2 tag. Candidate sample variation was below
0.4% at every concurrency. This was a consistent regression beyond the
unchanged 2% allowance, not an unstable-series rejection.

The job checked out PR merge commit ``006eec329cf862862b49b8f3e022f6af5abefc3d``;
its source tree matches the recorded PR head. Both paths used PHP-FPM
8.4.26, OPcache and production-only authoritative autoloaders.

The repair skips empty cheap/medium phases and reuses the compiled implicit
rule, name and placeholder arrays. Only an absent field with a ``filled``
rule needs filtered arrays. Existing per-callback cancellation guards stay
in place. New regressions cover missing/present conditional fields across
validator reuse and cancellation in each non-implicit cost phase, for both
mutable and compiled validators. Native PHP 8.4/8.5 suites pass with
383 tests and 4,032 assertions; PHPForge processing, detailed checks and the
release guard pass with unchanged detectors. The documentation builds with
Sphinx warnings treated as errors.

Acceptance of this repair requires fresh hosted checks for its exact PR
revision, including the same HTTP fixture, 2% regression allowance and 5%
sample-variation limit. Component timing diagnostics are not HTTP acceptance
evidence. Consult the `PR checks
<https://github.com/infocyph/ReqShield/pull/17/checks>`_ for the current
revision's artifacts; the earlier failure and local measurements above
remain historical records.

All six hosted workflows subsequently passed for the repaired code candidate
``d15293a3dc8b2261fedc4965f880d6e7326f2744``:
`PHPForge QA <https://github.com/infocyph/ReqShield/actions/runs/37887000787>`_,
`release guard <https://github.com/infocyph/ReqShield/actions/runs/37887000388>`_,
`documentation <https://github.com/infocyph/ReqShield/actions/runs/37887000270>`_,
`SQL parity <https://github.com/infocyph/ReqShield/actions/runs/37887000296>`_,
`persistent soak <https://github.com/infocyph/ReqShield/actions/runs/37887000299>`_
and `HTTP throughput <https://github.com/infocyph/ReqShield/actions/runs/37887000387>`_.
The HTTP artifact records merge commit
``2b01214e0fe4ab37d6268df2cc9ec61f92607a3d``, whose tree matches that PR head.
Against 3.2, median successful RPM changes were **+1.12%, -0.02% and +1.10%**
at concurrency 1, 4 and 8. Every series passed the unchanged 2% regression
and 5% sample-variation gates; candidate variation was at most 0.92%.
PHP-FPM was 8.4.26 and peak live FPM process-tree RSS was 128 MiB.
The 300-second soak passed with 28,197 request cycles, 1,485 cancellation
checks, 10 MiB peak PHP allocation and zero retained active tasks or database
bindings. These results close the reproduced executor throughput regression
for the recorded code candidate. Final revision checks and deployment-specific
application acceptance remain required before a release.

The earlier hosted certificates cover their recorded commits. Revalidate
the repaired final commit before release; do not reuse those certificates
as evidence for subsequent source changes.

Release Acceptance Gates
------------------------

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
