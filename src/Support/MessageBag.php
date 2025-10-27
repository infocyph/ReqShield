<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Countable;

/**
 * MessageBag
 *
 * Container for validation error messages with convenient access methods.
 */
class MessageBag implements Countable
{
    protected array $messages = [];

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
     */
    public function add(string $key, string $message): self
    {
        if (!isset($this->messages[$key])) {
            $this->messages[$key] = [];
        }

        $this->messages[$key][] = $message;

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
     * Check if any messages exist
     */
    public function any(): bool
    {
        return !empty($this->messages);
    }

    /**
     * Get count of messages
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Get the first message for a key
     */
    public function first(?string $key = null): ?string
    {
        if ($key === null) {
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
     * Get all messages as flat array
     */
    public function flatten(): array
    {
        $flat = [];
        foreach ($this->messages as $messages) {
            $flat = array_merge($flat, $messages);
        }
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
     * Check if bag is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->messages);
    }

    /**
     * Merge another message bag
     */
    public function merge(MessageBag $bag): self
    {
        foreach ($bag->all() as $key => $messages) {
            foreach ($messages as $message) {
                $this->add($key, $message);
            }
        }

        return $this;
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
    public function toJson(): string
    {
        return json_encode($this->messages);
    }

    /**
     * Get messages as formatted string
     */
    public function toString(string $format = '- :message'): string
    {
        $output = [];
        foreach ($this->messages as $key => $messages) {
            foreach ($messages as $message) {
                $output[] = str_replace([':key', ':message'], [$key, $message], $format);
            }
        }
        return implode("\n", $output);
    }

    /**
     * Get unique messages (remove duplicates)
     */
    public function unique(): self
    {
        $unique = [];
        foreach ($this->messages as $key => $messages) {
            $unique[$key] = array_unique($messages);
        }

        return new self($unique);
    }
}
