Installation
============

You can install ReqShield via Composer.

.. code-block:: bash

    composer require infocyph/reqshield

Requirements
------------

ReqShield has the following requirements:

* **PHP 8.4+**
* **``ext-hash`` with ``xxh3`` support** (used for schema/payload-shape cache keys)
* **``ext-mbstring``** (used by string validation and normalization)
* **``ext-fileinfo``** (used for strict server-side MIME detection)

The library is type-safe and uses modern PHP features, requiring version 8.4 or newer.

Database Integrations
---------------------

Database validation does not require a specific database package. Supply an
implementation of ``Infocyph\ReqShield\Contracts\DatabaseProvider`` using PDO,
DBLayer, Laravel, Doctrine, or another database layer. DBLayer 5 is a
development-only reference integration used by ReqShield's own tests; it is not
installed for normal consumers.
