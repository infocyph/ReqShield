Development Commands
====================

ReqShield defines Composer scripts for local development and CI-aligned checks.

Core Commands
-------------

.. code-block:: bash

    composer ic:tests
    composer ic:tests:details
    composer ic:test:code
    composer ic:test:lint
    composer ic:test:sniff
    composer ic:test:static
    composer ic:test:security
    composer ic:test:duplicates
    composer ic:benchmark
    composer ic:bench:quick
    composer ic:process

What They Run
-------------

* ``ic:tests``: full quality suite
* ``ic:tests:details``: expanded non-shortcut quality suite
* ``ic:test:code``: Pest test run
* ``ic:test:lint``: Pint check mode
* ``ic:test:sniff``: PHPCS
* ``ic:test:static``: PHPStan
* ``ic:test:security``: Psalm security analysis
* ``ic:test:duplicates``: duplicate code detection
* ``ic:benchmark`` / ``ic:bench:quick``: PhpBench benchmark suite
* ``ic:process``: Rector + Pint + PHPCBF processing pipeline

Git Hooks
---------

CaptainHook is wired through Composer:

.. code-block:: bash

    composer ic:hooks

Hooks are also installed automatically on ``post-autoload-dump``.
