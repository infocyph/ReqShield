ReqShield: Fast, Modern PHP Request Validation
===============================================

**ReqShield** is a fast, modern PHP request validation and sanitization library. It features schema-based rules, fail-fast execution, typed input, and is PSR-7 friendly.

This documentation will guide you through installation, basic usage, and all the advanced features ReqShield has to offer, from nested validation to custom database rules.

Getting Started
---------------

Here is a simple example of validating a set of data.

.. code-block:: php

    <?php
    use Infocyph\ReqShield\Validator;

    $validator = Validator::make([
        'email' => 'required|email|max:255',
        'username' => 'required|string|min:3|max:50',
        'age' => 'required|integer|min:18|max:120',
    ]);

    $data = [
        'email' => 'john@example.com',
        'username' => 'johndoe',
        'age' => 25,
    ];

    $result = $validator->validate($data);

    if ($result->passes()) {
        echo "✓ Validation passed!\n";
        // $result->validated() contains only the validated data
        print_r($result->validated());
    } else {
        echo "✗ Validation failed:\n";
        // $result->errors() contains an array of errors
        print_r($result->errors());
    }

Table of Contents
-----------------

.. toctree::
   :maxdepth: 2
   :caption: Getting Started

   installation
   basic-usage
   handling-results

.. toctree::
   :maxdepth: 2
   :caption: Core Features

   nested-validation
   sanitization
   database-rules
   custom-rules
   helper-functions

.. toctree::
   :maxdepth: 2
   :caption: Reference

   rule-reference
