<?php
/**
 * Created by qoliber
 *
 * @category    Qoliber
 * @package     Qoliber_Trident
 * @author      Jakub Winkler <jwinkler@qoliber.com>
 */

declare(strict_types=1);

namespace Qoliber\Trident\Cache;

/**
 * Resolves cache tags from various sources (entities, requests, etc.)
 */
class TagResolver
{
    /** @var array<string, callable> */
    private array $resolvers = [];

    /**
     * Register a tag resolver for a specific type
     *
     * @param callable(mixed): array<string> $resolver
     */
    public function register(string $type, callable $resolver): self
    {
        $this->resolvers[$type] = $resolver;

        return $this;
    }

    /**
     * Resolve tags for an entity/object
     *
     * @return array<string>
     */
    public function resolve(string $type, mixed $entity): array
    {
        if (!isset($this->resolvers[$type])) {
            // Default resolution: try to get ID from entity
            return $this->defaultResolve($type, $entity);
        }

        $tags = ($this->resolvers[$type])($entity);

        return is_array($tags) ? $tags : [$tags];
    }

    /**
     * Resolve tags for multiple entities
     *
     * @param iterable<mixed> $entities
     * @return array<string>
     */
    public function resolveMany(string $type, iterable $entities): array
    {
        $tags = [];
        foreach ($entities as $entity) {
            $tags = array_merge($tags, $this->resolve($type, $entity));
        }

        return array_unique($tags);
    }

    /**
     * @return array<string>
     */
    private function defaultResolve(string $type, mixed $entity): array
    {
        $id = $this->extractId($entity);

        if ($id !== null) {
            return ["{$type}.{$id}"];
        }

        return ["{$type}.list"];
    }

    private function extractId(mixed $entity): int|string|null
    {
        if (is_scalar($entity)) {
            return (string) $entity;
        }

        if (is_array($entity)) {
            return $entity['id'] ?? null;
        }

        if (is_object($entity)) {
            if (method_exists($entity, 'getId')) {
                return $entity->getId();
            }

            if (property_exists($entity, 'id')) {
                return $entity->id;
            }
        }

        return null;
    }
}
