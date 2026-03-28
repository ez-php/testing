<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;

/**
 * Fluent factory for building and persisting Entity instances in tests.
 *
 * Default attribute values may be static values or callables. Callables are
 * invoked once per entity instance, which makes it easy to produce unique
 * values (e.g. sequential IDs, random strings) without collisions across
 * instances.
 *
 * Usage:
 *   $factory = new EntityFactory(User::class, $userRepo, [
 *       'name'  => 'Alice',
 *       'email' => fn () => uniqid('user_') . '@example.com',
 *   ]);
 *
 *   $user  = $factory->make();                    // attributes set, not persisted
 *   $user  = $factory->create();                  // persisted via repository->save()
 *   $users = $factory->createMany(5, ['role' => 'admin']);
 *
 * Persistence requires an AbstractRepository because Entity instances have
 * no database awareness — the repository handles all INSERT/UPDATE operations.
 *
 * @template T of Entity
 *
 * @package EzPhp\Testing
 */
final readonly class EntityFactory
{
    /**
     * @param class-string<T>        $entityClass
     * @param AbstractRepository<T>  $repository
     * @param array<string, mixed>   $defaults    Default attribute values; scalar or callable.
     */
    public function __construct(
        private string $entityClass,
        private AbstractRepository $repository,
        private array $defaults = [],
    ) {
    }

    /**
     * Build an entity instance without persisting it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return T
     */
    public function make(array $overrides = []): Entity
    {
        $attributes = $this->resolveAttributes($overrides);

        return new $this->entityClass($attributes);
    }

    /**
     * Build and persist an entity instance via the repository.
     *
     * @param array<string, mixed> $overrides
     *
     * @return T
     */
    public function create(array $overrides = []): Entity
    {
        $entity = $this->make($overrides);
        $this->repository->save($entity);

        return $entity;
    }

    /**
     * Build multiple entity instances without persisting them.
     *
     * @param int                  $count
     * @param array<string, mixed> $overrides
     *
     * @return list<T>
     */
    public function makeMany(int $count, array $overrides = []): array
    {
        $entities = [];

        for ($i = 0; $i < $count; $i++) {
            $entities[] = $this->make($overrides);
        }

        return $entities;
    }

    /**
     * Build and persist multiple entity instances.
     *
     * @param int                  $count
     * @param array<string, mixed> $overrides
     *
     * @return list<T>
     */
    public function createMany(int $count, array $overrides = []): array
    {
        $entities = [];

        for ($i = 0; $i < $count; $i++) {
            $entities[] = $this->create($overrides);
        }

        return $entities;
    }

    /**
     * Resolve defaults by invoking any callable values, then merge overrides.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function resolveAttributes(array $overrides): array
    {
        $resolved = [];

        foreach ($this->defaults as $key => $value) {
            $resolved[$key] = is_callable($value) ? $value() : $value;
        }

        return array_merge($resolved, $overrides);
    }
}
