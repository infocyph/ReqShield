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
