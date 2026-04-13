Development Commands
====================

ReqShield defines Composer scripts for local development and CI-aligned checks.

Core Commands
-------------

.. code-block:: bash

    composer test
    composer tests
    composer test:code
    composer test:lint
    composer test:refactor
    composer test:security
    composer benchmark
    composer lint
    composer refactor
    composer security:scan

What They Run
-------------

* ``test``: Pest test run
* ``tests``: combined suite (code, lint, refactor dry-run, security analysis)
* ``test:code``: Pest in parallel mode
* ``test:lint`` / ``lint``: Pint
* ``test:refactor`` / ``refactor``: Rector
* ``test:security`` / ``security:scan``: Psalm security analysis
* ``benchmark``: PhpBench validator benchmark suite

Git Hooks
---------

CaptainHook is wired through Composer:

.. code-block:: bash

    composer git:hook

Hooks are also installed automatically on ``post-autoload-dump``.
