<?php

declare(strict_types=1);

namespace Patro\Shared;

use RuntimeException;

/**
 * Conteneur minimal pour composer les dépendances de l'application.
 */
final class Container
{
    /** @var array<string, callable|object> */
    private array $definitions = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function set(string $id, callable|object $definition): void
    {
        $this->definitions[$id] = $definition;
        unset($this->instances[$id]);
    }

    public function singleton(string $id, callable $factory): void
    {
        $this->set($id, function () use ($id, $factory): object {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $factory($this);
            }

            return $this->instances[$id];
        });
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!array_key_exists($id, $this->definitions)) {
            throw new RuntimeException('Service non enregistré : ' . $id);
        }

        $definition = $this->definitions[$id];
        if (is_callable($definition)) {
            return $definition($this);
        }

        return $definition;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions) || array_key_exists($id, $this->instances);
    }
}
