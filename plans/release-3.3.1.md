# ReqShield 3.3.1 audit and release plan

Audit date: 2026-11-01. Starting commit: `427601b27ad270bbac401643c9197d3b13862669`.

Follow `vendor/infocyph/phpforge/resources/engineering-principles.md` and its
agent workflow. Preserve public signatures, host ownership of Runwire contexts,
database-native comparison semantics, and all existing quality/performance budgets.

## Findings and required changes

1. **Security: MIME rules permit stream-wrapper I/O.** A benign registered wrapper
   supplied as an upload's `tmp_name` is statted and opened by `mimes:txt` and
   `mimetypes:text/plain`, and both rules accept its content. Image rules already
   reject that path without I/O. Reject empty, NUL-containing and wrapper paths
   before MIME filesystem access; regress wrapper non-invocation and real local
   MIME detection. This closes an unexpected I/O boundary; actual remote access
   depends on installed wrappers and application exposure of upload metadata.
2. **Packaging: Composer archives include local/development payloads.** The starting
   archive contains 11,048 entries, including 10,569 under `vendor/`, 276 under
   `graphify-out/`, `composer.lock`, and local configuration directories. Add
   Composer archive exclusions and matching Git export exclusions, rebuild the
   archive, compare its runtime files with tracked source, and smoke-test an
   isolated production consumer.
3. **Dependency maintenance.** Change development Runwire `2.1.1` to `^3.0` and
   DBLayer `^6.0` to `^6.1.3`. Remove the unrelated ArrayKit conflict: ReqShield
   contains no ArrayKit calls, and DBLayer 6.1.3 itself requires `^5.3.2`.
   Preserve the native DBLayer API floor/conflict `<6.0`; distinguish that
   supported API floor from the newer development/reference dependency.
   Keep Runwire and DBLayer optional for production consumers. Update current
   installation/integration documentation and workflow labels; retain historical
   3.2/3.3 upgrade and benchmark evidence as historical records.
4. **Development dependency health.** Composer's live audit reports no advisories,
   but `doctrine/annotations` is abandoned. Identify its owner and record any
   required PHPForge-level follow-up; do not bypass audit policy or patch vendor.
   Confirmed owner: `phpbench/phpbench:1.7.0 -> doctrine/annotations:^2.0`.

## Maintainability follow-up

PHPProbe reports the same six pre-existing clone groups (411 lines, 2.26%) on
the baseline and candidate. Review them by behavior before refactoring:

- The two enum-case ranges in `BuiltinRule` repeat declaration syntax rather
  than executable behavior; preserve public enum cases.
- Constructor/clone initialization in `Validator` shares collaborator setup,
  but clone also detaches rule state and invalidates caches.
- Enum conversion in `HasValidatorSchemaCasting` and `ValidatedInput` is a
  candidate for one owner, preserving throwing versus default-return behavior.
- `SchemaRegistry::define()` and `replace()` share storage preparation but
  enforce different existence contracts.
- `allowUnknown()`/`stripUnknown()` and cast/sanitizer setters share method
  shapes while changing different policy state.

Centralize actual repeated behavior only with focused contract regressions and
matched performance evidence. These are existing non-blocking findings; broad
refactoring is separate from the security/dependency patch. No detector scope,
threshold, baseline or suppression was changed.

## Verification and release gates

- [x] Starting detailed PHPForge suite: 383 tests / 4,032 assertions; syntax,
  references, duplicates, comments, formatting, architecture, PHPStan, Psalm and
  Rector pass on host PHP 8.5.4.
- [x] Reproduce MIME wrapper I/O and archive contamination before changes.
- [x] Resolve the released dependency chain and run security/integration tests:
  Runwire 3.0, DBLayer 6.1.3, ArrayKit 5.3.2; 387 tests / 4,046 assertions.
- [x] Run `composer ic:process`, review the complete diff, then
  `composer ic:tests:details` and `composer ic:release:guard`.
- [x] Verify Composer/Git archive payloads and isolated `--no-dev` consumers,
  including normal validation with neither optional integration installed.
  The final Composer archive has 199 entries and all 193 tracked runtime files,
  byte-identical to the working tree. Production consumers pass on PHP 8.4.26
  and 8.5.4, with and without the requested integration chain. An additional
  consumer confirms DBLayer 6.0 with Runwire 3.0 still validates, restores
  bindings and propagates cancellation.
- [x] Build Sphinx with warnings as errors and run PHPBench: 40 subjects,
  zero failures/errors. These are component measurements, not host RPM proof.
- [x] Run the host-owned Runwire/DBLayer 300-second soak: 28,010 cycles,
  1,475 cancellations, 10 MiB peak PHP allocation, zero retained tasks/bindings.
- [x] Run SQL parity probes on disposable MySQL 9.7.2 and PostgreSQL 18.6:
  11 typed candidates, `exists` and `unique`, both pass.
- [x] Stable-dependency suites on host PHP 8.5.4 and container PHP 8.4.26:
  387 tests / 4,046 assertions each. Lowest-dependency PHP 8.5 CI: all 11 checks
  pass with the same PHPForge/PHPProbe releases and the released integration floors.
  The direct lowest-dependency PHP 8.4.26 Pest suite also passes all 387 tests /
  4,046 assertions; full QA on that minimal container needs Git installed.
- [ ] Obtain passing stable HTTP RPM evidence. The local matched comparison
  against the starting commit failed acceptance: c1 -5.79% with 12–13.18% CV,
  c4 +1.52% with 0.92–1.77% CV, c8 -2.24% with 4.24–5.17% CV. The harness marks
  c1/c8 unstable and c4 accepted; this is inconclusive evidence, not a proven
  source regression or a passing performance gate. Recheck the unchanged
  candidate in a quiet matched environment with the existing 2% budget.
- [ ] Validate PHP 8.4 and 8.5 stable/lowest dependency lanes, database engine
  parity, docs, strict release guard, sustained HTTP RPM and 300-second soak
  against the final committed candidate in hosted CI.
- [ ] User reviews, commits/merges, tags and publishes the release.

## Release classification

Recommend **3.3.1**: security/packaging repairs and development dependency updates,
without new public APIs or a raised existing optional DBLayer API floor. Revisit
the version only if further findings require new behavior or public contracts.
Local success does not certify an uncommitted candidate in hosted CI.

## Reproduction notes

The lowest-dependency environment targets the available PHP 8.4.26 patch release.
Trying a simulated 8.4.0 development platform fails because PHPForge's Symfony
Console requires at least 8.4.1; this does not change ReqShield's production
PHP requirement. QA checkout copies need Git and an index honoring `.gitignore`:
without them, PHPProbe's recursive fallback scans vendor files. PHPCS also
excludes absolute `/tmp/` paths by default. An indexed isolated checkout under
the ignored `var/` directory passes the complete PHP 8.5 lowest CI lane without
changing any detector configuration. Remove that isolated checkout before
running the root project's whole-tree analyzers.
