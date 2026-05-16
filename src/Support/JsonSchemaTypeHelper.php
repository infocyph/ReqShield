<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class JsonSchemaTypeHelper
{
    /** @param array<int|string, mixed> $property */
    public static function applyNullableType(array &$property): void
    {
        $type = $property['type'] ?? 'string';
        if (is_string($type)) {
            $property['type'] = [$type, 'null'];

            return;
        }

        if (!is_array($type)) {
            return;
        }

        $types = array_values(array_filter(
            $type,
            is_string(...),
        ));

        if (!in_array('null', $types, true)) {
            $types[] = 'null';
        }

        $property['type'] = $types;
    }
}
