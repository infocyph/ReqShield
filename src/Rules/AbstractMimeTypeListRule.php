<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractMimeTypeListRule extends BaseRule
{
    /** @var list<string> */
    protected array $types;

    public function __construct(string ...$types)
    {
        $this->types = array_values($types);
    }

    protected function resolveMimeType(mixed $value): ?string
    {
        $path = $this->getUploadedFilePath($value);
        if (is_string($path)) {
            $detected = $this->detectMimeTypeFromPath($path);
            if ($detected !== null) {
                return $detected;
            }
        }

        $clientMimeType = $this->getUploadedFileClientMediaType($value);

        return is_string($clientMimeType) && $clientMimeType !== ''
            ? $clientMimeType
            : null;
    }
}
