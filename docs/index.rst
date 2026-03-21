ReqShield: Fast PHP Validation and Sanitization
===============================================

**ReqShield** is a schema-based request validator and sanitizer for modern PHP apps.
It supports nested/wildcard validation, localized/custom messages, batched database rules, sanitizer pipelines, typed output, and schema export.

Quick Start
-----------

.. code-block:: php

    <?php
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'age' => 'required|integer|min:18',
    ])->setSanitizers([
        'email' => ['trim', 'lowercase'],
    ])->setCasts([
        'age' => 'integer',
    ]);

    $result = $validator->validate([
        'email' => '  USER@EXAMPLE.COM ',
        'age' => '21',
    ]);

    if ($result->passes()) {
        print_r($result->typed()); // ['email' => 'user@example.com', 'age' => 21]
    } else {
        print_r($result->errors());
    }

Table of Contents
-----------------

.. toctree::
   :maxdepth: 2
   :caption: Getting Started

   installation
   quick-usage
   basic-usage
   handling-results

.. toctree::
   :maxdepth: 2
   :caption: Core Features

   advanced-features
   nested-validation
   sanitization
   database-rules
   custom-rules
   helper-functions

.. toctree::
   :maxdepth: 2
   :caption: Reference

   rule-reference
