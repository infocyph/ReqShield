<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Enums\MimeDetectionMode;

abstract class AbstractMimeTypeListRule extends BaseRule
{
    protected MimeDetectionMode $detectionMode = MimeDetectionMode::Strict;

    /** @var list<string> */
    protected array $types;

    public function __construct(string ...$types)
    {
        $this->types = array_values($types);
    }

    public function compatible(): static
    {
        $clone = clone $this;
        $clone->detectionMode = MimeDetectionMode::Compatible;

        return $clone;
    }

    public function strict(): static
    {
        $clone = clone $this;
        $clone->detectionMode = MimeDetectionMode::Strict;

        return $clone;
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

        if ($this->detectionMode === MimeDetectionMode::Strict) {
            return null;
        }

        $clientMimeType = $this->getUploadedFileClientMediaType($value);

        return is_string($clientMimeType) && $clientMimeType !== ''
            ? $clientMimeType
            : null;
    }
}
