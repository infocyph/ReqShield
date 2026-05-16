<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * @implements ArrayAccess<string, mixed>
 * @implements Iterator<string, array<int, string>>
 */
class MessageBag implements ArrayAccess, Countable, Iterator, JsonSerializable, \Stringable
{
    // Cache for expensive operations
    /** @var array<int, string>|null */
    protected ?array $flatCache = null;

    /** @var array<int, string> */
    protected array $iteratorKeys = [];

    protected int $iteratorPosition = 0;

    protected ?int $messageCount = null;

    /** @param array<string, array<int, string>> $messages */
    public function __construct(protected array $messages = []) {}

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @param array<int|string, mixed> $messages
     */
    public static function fromFlat(
        array $messages,
        string $defaultKey = 'error',
    ): self {
        $bag = new self();

        foreach ($messages as $key => $message) {
            if (is_numeric($key)) {
                $bag->add($defaultKey, self::normalizeMessage($message));
            } elseif (is_string($key)) {
                $bag->add($key, self::normalizeMessage($message));
            }
        }

        return $bag;
    }

    /** @param array<int|string, mixed> $messages */
    public static function make(array $messages): self
    {
        // Check if already in correct format
        if (self::isValidFormat($messages)) {
            $normalized = [];
            foreach ($messages as $key => $value) {
                if (!is_string($key) || !is_array($value)) {
                    continue;
                }

                $normalized[$key] = array_values(array_map(
                    self::normalizeMessage(...),
                    $value,
                ));
            }

            return new self($normalized);
        }

        // Convert flat array to grouped format
        return self::fromFlat($messages);
    }

    public function add(string $key, string $message): self
    {
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = [];
        }

        $this->messages[$key][] = $message;

        // Invalidate caches
        $this->invalidateCaches();

        return $this;
    }

    /** @param array<int, string> $messages */
    public function addMany(string $key, array $messages): self
    {
        $messages = array_values($messages);

        if (empty($messages)) {
            return $this;
        }

        if (!isset($this->messages[$key])) {
            $this->messages[$key] = $messages;
        } else {
            // Use array_push for better performance than multiple assignments
            array_push($this->messages[$key], ...$messages);
        }

        $this->invalidateCaches();

        return $this;
    }

    /** @return array<string, array<int, string>> */
    public function all(): array
    {
        return $this->messages;
    }

    public function any(): bool
    {
        return !empty($this->messages);
    }

    public function clear(): self
    {
        $this->messages = [];
        $this->invalidateCaches();

        return $this;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function current(): mixed
    {
        $key = $this->iteratorKeys[$this->iteratorPosition] ?? null;

        return is_string($key) ? ($this->messages[$key] ?? []) : [];
    }

    /** @param array<int, string> $keys */
    public function except(array $keys): self
    {
        $filtered = array_diff_key($this->messages, array_flip($keys));

        return new self($filtered);
    }

    public function filter(callable $callback): self
    {
        $filtered = array_filter(
            $this->messages,
            $callback,
            ARRAY_FILTER_USE_BOTH,
        );

        return new self($filtered);
    }

    public function first(?string $key = null): ?string
    {
        if ($key === null) {
            // Get first message from any field
            foreach ($this->messages as $messages) {
                if (!empty($messages)) {
                    return (string) $messages[0];
                }
            }

            return null;
        }

        $messages = $this->get($key);

        return $messages[0] ?? null;
    }

    /** @return array<int, string> */
    public function flatten(): array
    {
        if ($this->flatCache !== null) {
            return $this->flatCache;
        }

        // OPTIMIZATION: Use array_push with spread operator instead of array_merge
        $flat = [];
        foreach ($this->messages as $messages) {
            if ($messages === []) {
                continue;
            }
            array_push($flat, ...$messages);
        }

        $this->flatCache = $flat;

        return $flat;
    }

    /** @return array<int, string> */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    public function has(string $key): bool
    {
        return isset($this->messages[$key]) && !empty($this->messages[$key]);
    }

    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->messages);
    }

    /** @return array<string, array<int, string>> */
    public function jsonSerialize(): array
    {
        return $this->messages;
    }

    public function key(): mixed
    {
        return $this->iteratorKeys[$this->iteratorPosition] ?? '';
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    public function last(?string $key = null): ?string
    {
        if ($key === null) {
            // Get last message from any field
            $lastMessage = null;
            foreach ($this->messages as $messages) {
                if (!empty($messages)) {
                    $lastMessage = end($messages);
                }
            }

            return $lastMessage === null ? null : (string) $lastMessage;
        }

        $messages = $this->get($key);

        $index = count($messages) - 1;

        return $index >= 0 ? (string) $messages[$index] : null;
    }

    /** @param callable(string): string $callback */
    public function map(callable $callback): self
    {
        $mapped = array_map(fn($messages) => array_map($callback, $messages), $this->messages);

        return new self($mapped);
    }

    public function merge(MessageBag $bag): self
    {
        foreach ($bag->all() as $key => $messages) {
            if ($messages === []) {
                continue;
            }

            if (!isset($this->messages[$key])) {
                $this->messages[$key] = $messages;
            } else {
                // Use array_push with spread operator - much faster than array_merge!
                array_push($this->messages[$key], ...$messages);
            }
        }

        $this->invalidateCaches();

        return $this;
    }

    public function messageCount(): int
    {
        if ($this->messageCount !== null) {
            return $this->messageCount;
        }

        $count = 0;
        foreach ($this->messages as $messages) {
            $count += count($messages);
        }

        $this->messageCount = $count;

        return $count;
    }

    public function next(): void
    {
        $this->iteratorPosition++;
    }

    // ============================================
    // ArrayAccess Implementation
    // ============================================

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $key = (string) $offset;
        if ($key === '') {
            return;
        }

        if (is_array($value)) {
            $messages = array_values(array_filter(
                $value,
                is_string(...),
            ));
            $this->set($key, $messages);
        } else {
            $this->add($key, self::normalizeMessage($value));
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove((string) $offset);
    }

    /** @param array<int, string> $keys */
    public function only(array $keys): self
    {
        $filtered = array_intersect_key($this->messages, array_flip($keys));

        return new self($filtered);
    }

    public function remove(string $key): self
    {
        if (isset($this->messages[$key])) {
            unset($this->messages[$key]);
            $this->invalidateCaches();
        }

        return $this;
    }

    // ============================================
    // Iterator Implementation
    // ============================================

    public function rewind(): void
    {
        $this->iteratorKeys = array_keys($this->messages);
        $this->iteratorPosition = 0;
    }

    /** @param array<int, string> $messages */
    public function set(string $key, array $messages): self
    {
        $this->messages[$key] = $messages;
        $this->invalidateCaches();

        return $this;
    }

    /** @return array<string, array<int, string>> */
    public function toArray(): array
    {
        return $this->messages;
    }

    public function toGroupedString(
        string $keyFormat = ':key:',
        string $messageFormat = '  - :message',
        string $separator = "\n",
    ): string {
        if ($this->isEmpty()) {
            return '';
        }

        $output = '';
        $isFirst = true;

        foreach ($this->messages as $key => $messages) {
            if (!$isFirst) {
                $output .= $separator;
            }

            $output .= str_replace(':key', (string) $key, $keyFormat) . $separator;

            foreach ($messages as $message) {
                $output .= str_replace(
                    [':key', ':message'],
                    [(string) $key, (string) $message],
                    $messageFormat,
                ) . $separator;
            }

            $isFirst = false;
        }

        return rtrim($output, $separator);
    }

    public function toHtml(string $listType = 'ul'): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $html = "<{$listType}>";

        foreach ($this->flatten() as $message) {
            $html .= '<li>' . htmlspecialchars(
                (string) $message,
                ENT_QUOTES,
                'UTF-8',
            ) . '</li>';
        }

        return $html . "</{$listType}>";
    }

    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->messages, $options);

        return is_string($json) ? $json : '{}';
    }

    /** @return array<string, string> */
    public function toSimpleArray(): array
    {
        $simple = [];

        foreach ($this->messages as $key => $messages) {
            $simple[(string) $key] = $messages[0] ?? '';
        }

        return $simple;
    }

    public function toString(
        string $format = '- :message',
        string $separator = "\n",
    ): string {
        if ($this->isEmpty()) {
            return '';
        }

        $output = '';
        $isFirst = true;

        foreach ($this->messages as $key => $messages) {
            foreach ($messages as $message) {
                if (!$isFirst) {
                    $output .= $separator;
                }
                $output .= str_replace(
                    [':key', ':message'],
                    [(string) $key, (string) $message],
                    $format,
                );
                $isFirst = false;
            }
        }

        return $output;
    }

    public function unique(): self
    {
        $unique = array_map(fn($messages) => array_values(array_unique($messages)), $this->messages);

        return new self($unique);
    }

    public function valid(): bool
    {
        return isset($this->iteratorKeys[$this->iteratorPosition]);
    }

    /** @param array<int|string, mixed> $messages */
    protected static function isValidFormat(array $messages): bool
    {
        return array_all($messages, fn($value) => is_array($value));
    }

    protected static function normalizeMessage(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_scalar($message) || $message === null) {
            return (string) $message;
        }

        $json = json_encode($message);

        return is_string($json) ? $json : '';
    }

    // ============================================
    // Helper Methods
    // ============================================

    protected function invalidateCaches(): void
    {
        $this->flatCache = null;
        $this->messageCount = null;
    }
}
