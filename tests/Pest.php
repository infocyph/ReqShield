<?php

use Infocyph\ReqShield\Validator;

uses()->group('reqshield');

function valid(array $data, array|string $rules): bool
{
    return Validator::make($rules)->validate($data)->passes();
}

function errors(array $data, array|string $rules): array
{
    return Validator::make($rules)->validate($data)->errors();
}
