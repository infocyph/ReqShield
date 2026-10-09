Installation
============

Install ReqShield through Composer:

.. code-block:: bash

    composer require infocyph/reqshield

Requirements
------------

* PHP 8.4 or newer
* ``ext-hash`` (canonical input shape hashing uses SHA-256)
* ``ext-mbstring`` for string validation
* ``ext-fileinfo`` for MIME detection

Ordinary validation requires neither DBLayer nor Runwire.

Optional Database and Runwire Integrations
------------------------------------------

DBLayer **6.0** is the intended native database reference for 3.3.
It remains optional for users who do not need database validation:

.. code-block:: bash

    composer require 'infocyph/dblayer:^6.0'

For opt-in, host-owned Runwire validation context support, install
Runwire **2.1.1**:

.. code-block:: bash

    composer require 'infocyph/runwire:2.1.1'

ReqShield's production dependencies do not require either package. Use
``validate()`` without Runwire, or ``validateWithRunwire()`` when the
application passes an existing host runtime/request/scope. The native
DBLayer 5.1 bridge remains usable for ordinary database validations
without DBLayer 6's Runwire query binding.

See :doc:`runwire-integration` and :doc:`database-rules` for details.
