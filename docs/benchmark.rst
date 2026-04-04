Benchmarking
============

ReqShield includes a benchmark script for validator throughput and latency profiles.

Run Benchmark
-------------

Use Composer:

.. code-block:: bash

    composer benchmark

This runs:

* ``benchmark/validator_bench.php``

What It Measures
----------------

Current benchmark scenarios:

* ``flat-fast-rules`` (flat payload, mostly cheap rules)
* ``nested-wildcard`` (nested payload with wildcard path expansion)
* ``db-heavy-batched`` (database-backed rules using batched execution)

Output Metrics
--------------

Each scenario reports:

* ``iterations``
* ``throughput (ops/s)``
* ``p50 (ms)``
* ``p95 (ms)``
* ``peak MB``

Progress Display
----------------

Progress output is shown as total percentage only:

* ``[progress] 0%`` ... ``[progress] 100%``

This avoids noisy per-step logs while still showing liveness during longer runs.
