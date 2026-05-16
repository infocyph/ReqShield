Benchmarking
============

ReqShield includes a PhpBench benchmark suite for validator throughput and latency profiles.

Run Benchmark
-------------

Use Composer:

.. code-block:: bash

    composer ic:benchmark
    composer ic:bench:quick

This runs the PhpBench suite in:

* ``benchmarks/ValidatorBench.php``
* ``benchmarks/ResolverBench.php``

What It Measures
----------------

Current benchmark scenarios:

* ``flat-fast-rules`` (flat payload, mostly cheap rules)
* ``nested-wildcard`` (nested payload with wildcard path expansion)
* ``db-heavy-batched`` (database-backed rules using batched execution)
* ``resolver-enum-resolve`` (``BuiltinRule::resolve()`` token lookup)
* ``resolver-compiler-cached`` (cached compiler rule-class resolution)
* ``resolver-token-map-lookup`` (direct token-to-class map lookup)

Output Metrics
--------------

Each scenario reports:

* ``revs`` (revolutions per iteration)
* ``its`` (iterations)
* ``mem_peak``
* ``mode`` (dominant execution time)
* ``rstdev`` (relative standard deviation)

Output Format
-------------

PhpBench prints aggregate timing and memory reports per benchmark subject.
