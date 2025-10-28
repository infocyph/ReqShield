<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Countable;
use Iterator;
use ArrayAccess;
use JsonSerializable;

class MessageBag implements Countable, Iterator, ArrayAccess, JsonSerializable
{
    protected array $messages = [];

    // Cache for expensive operations
    protected ?array $flatCache = null;
    protected ?int $messageCount = null;
    protected int $iteratorPosition = 0;
    protected array $iteratorKeys = [];

    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * Magic method to convert to string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Add a message to the bag
     * OPTIMIZED: Invalidate caches only when needed
     */
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

    /**
     * Add multiple messages at once
     * OPTIMIZED: Batch operation to minimize cache invalidations
     */
    public function addMany(string $key, array $messages): self
    {
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

    /**
     * Set messages for a key (replaces existing)
     */
    public function set(string $key, array $messages): self
    {
        $this->messages[$key] = $messages;
        $this->invalidateCaches();

        return $this;
    }

    /**
     * Remove messages for a specific key
     */
    public function remove(string $key): self
    {
        if (isset($this->messages[$key])) {
            unset($this->messages[$key]);
            $this->invalidateCaches();
        }

        return $this;
    }

    /**
     * Clear all messages
     */
    public function clear(): self
    {
        $this->messages = [];
        $this->invalidateCaches();

        return $this;
    }

    /**
     * Get all messages
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * Get all keys
     */
    public function keys(): array
    {
        return array_keys($this->messages);
    }

    /**
     * Get all messages as flat array
     * OPTIMIZED: Cached to avoid recomputation + no array_merge in loop!
     */
    public function flatten(): array
    {
        if ($this->flatCache !== null) {
            return $this->flatCache;
        }

        // OPTIMIZATION: Use array_push with spread operator instead of array_merge
        $flat = [];
        foreach ($this->messages as $messages) {
            array_push($flat, ...$messages);
        }

        $this->flatCache = $flat;

        return $flat;
    }

    /**
     * Get messages for a specific key
     */
    public function get(string $key): array
    {
        return $this->messages[$key] ?? [];
    }

    /**
     * Check if messages exist for a key
     */
    public function has(string $key): bool
    {
        return isset($this->messages[$key]) && !empty($this->messages[$key]);
    }

    /**
     * Check if any messages exist
     */
    public function any(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Check if bag is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Check if bag is not empty
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Get count of fields with messages
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get total count of all messages (across all fields)
     * OPTIMIZED: Cached result
     */
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

    /**
     * Get the first message for a key
     */
    public function first(?string $key = null): ?string
    {
        if ($key === null) {
            // Get first message from any field
            foreach ($this->messages as $messages) {
                if (!empty($messages)) {
                    return $messages[0];
                }
            }
            return null;
        }

        $messages = $this->get($key);
        return $messages[0] ?? null;
    }

    /**
     * Get the last message for a key
     */
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
            return $lastMessage;
        }

        $messages = $this->get($key);
        return $messages[count($messages) - 1] ?? null;
    }

    /**
     * Merge another message bag
     * OPTIMIZED: No array_merge in loop!
     */
    public function merge(MessageBag $bag): self
    {
        foreach ($bag->all() as $key => $messages) {
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

    /**
     * Get unique messages (remove duplicates)
     * OPTIMIZED: Only process if needed
     */
    public function unique(): self
    {
        $unique = array_map(function ($messages) {
            return array_values(array_unique($messages));
        }, $this->messages);

        return new self($unique);
    }

    /**
     * Filter messages by callback
     */
    public function filter(callable $callback): self
    {
        $filtered = array_filter($this->messages, $callback, ARRAY_FILTER_USE_BOTH);
        return new self($filtered);
    }

    /**
     * Map messages with callback
     */
    public function map(callable $callback): self
    {
        $mapped = array_map(function ($messages) use ($callback) {
            return array_map($callback, $messages);
        }, $this->messages);

        return new self($mapped);
    }

    /**
     * Get messages matching specific keys
     */
    public function only(array $keys): self
    {
        $filtered = array_intersect_key($this->messages, array_flip($keys));
        return new self($filtered);
    }

    /**
     * Get messages except specified keys
     */
    public function except(array $keys): self
    {
        $filtered = array_diff_key($this->messages, array_flip($keys));
        return new self($filtered);
    }

    /**
     * Transform messages to key => first message pairs
     */
    public function toSimpleArray(): array
    {
        $simple = [];

        foreach ($this->messages as $key => $messages) {
            $simple[$key] = $messages[0] ?? '';
        }

        return $simple;
    }

    /**
     * Get messages as formatted string
     * OPTIMIZED: Build string directly instead of array operations
     */
    public function toString(string $format = '- :message', string $separator = "\n"): string
    {
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
                $output .= str_replace([':key', ':message'], [$key, $message], $format);
                $isFirst = false;
            }
        }

        return $output;
    }

    /**
     * Get messages grouped by key with custom formatting
     */
    public function toGroupedString(string $keyFormat = ':key:', string $messageFormat = '  - :message', string $separator = "\n"): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $output = '';
        $isFirst = true;

        foreach ($this->messages as $key => $messages) {
            if (!$isFirst) {
                $output .= $separator;
            }

            $output .= str_replace(':key', $key, $keyFormat) . $separator;

            foreach ($messages as $message) {
                $output .= str_replace([':key', ':message'], [$key, $message], $messageFormat) . $separator;
            }

            $isFirst = false;
        }

        return rtrim($output, $separator);
    }

    /**
     * Get messages as HTML list
     */
    public function toHtml(string $listType = 'ul'): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $html = "<{$listType}>";

        foreach ($this->flatten() as $message) {
            $html .= '<li>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $html .= "</{$listType}>";

        return $html;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->messages;
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->messages, $options);
    }

    /**
     * JsonSerializable implementation
     */
    public function jsonSerialize(): array
    {
        return $this->messages;
    }

    // ============================================
    // Iterator Implementation
    // ============================================

    public function rewind(): void
    {
        $this->iteratorKeys = array_keys($this->messages);
        $this->iteratorPosition = 0;
    }

    public function current(): mixed
    {
        return $this->messages[$this->iteratorKeys[$this->iteratorPosition]];
    }

    public function key(): mixed
    {
        return $this->iteratorKeys[$this->iteratorPosition];
    }

    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    public function valid(): bool
    {
        return isset($this->iteratorKeys[$this->iteratorPosition]);
    }

    // ============================================
    // ArrayAccess Implementation
    // ============================================

    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_array($value)) {
            $this->set($offset, $value);
        } else {
            $this->add($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    // ============================================
    // Helper Methods
    // ============================================

    /**
     * Invalidate all caches
     */
    protected function invalidateCaches(): void
    {
        $this->flatCache = null;
        $this->messageCount = null;
    }

    /**
     * Create a new instance from flat array
     * Useful for migration from simple error arrays
     */
    public static function fromFlat(array $messages, string $defaultKey = 'error'): self
    {
        $bag = new self();

        foreach ($messages as $key => $message) {
            if (is_numeric($key)) {
                $bag->add($defaultKey, $message);
            } else {
                $bag->add($key, $message);
            }
        }

        return $bag;
    }

    /**
     * Create from array with multiple formats support
     */
    public static function make(array $messages): self
    {
        // Check if already in correct format
        if (self::isValidFormat($messages)) {
            return new self($messages);
        }

        // Convert flat array to grouped format
        return self::fromFlat($messages);
    }

    /**
     * Check if array is in valid MessageBag format
     */
    protected static function isValidFormat(array $messages): bool
    {
        return array_all($messages, fn($value) => is_array($value));
    }
}
