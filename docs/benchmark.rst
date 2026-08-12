Benchmarking
============

ReqShield uses PhpBench to keep construction, runtime, and memory costs visible.

.. code-block:: bash

    composer ic:benchmark
    composer ic:bench:quick

The suite covers:

* fresh construction at 1, 10, 50, and 100 fields;
* immutable compiled-plan reuse, string/object/custom/conditional schemas;
* flat passing and first/middle/final failure paths;
* fail-fast and collect-all behavior;
* active/inactive implicit rules and nullable/optional short circuits;
* optimized nested traversal and multiple wildcards;
* compiled sanitizer and cast pipelines;
* localized and wildcard failure messages;
* wildcard scaling through 10,000 matches;
* DBLayer 4.0.0 with batched SQLite checks at 1, 10, 100, 401, and 1,000 checks;
* built-in rule resolution.

PhpBench reports timing variance and peak memory. Compare results only on a
stable environment. PHPForge's benchmark-result validation and comparison
commands provide the regression-budget gate used for release baselines.
