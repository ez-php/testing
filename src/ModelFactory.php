<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Orm\Model;

/**
 * Fluent factory for building and persisting Model instances in tests.
 *
 * Default attribute values may be static values or callables. Callables are
 * invoked once per model instance, which makes it easy to produce unique values
 * (e.g. sequential IDs, random strings) without collisions across instances.
 *
 * Usage:
 *   $factory = new ModelFactory(User::class, [
 *       'name'  => 'Alice',
 *       'email' => fn () => uniqid('user_') . '@example.com',
 *   ]);
 *
 *   $user  = $factory->make();                    // attributes set, not persisted
 *   $user  = $factory->create();                  // persisted via Model::save()
 *   $users = $factory->createMany(5, ['role' => 'admin']);
 *
 * The model class must have a database registered via Model::setDatabase() before
 * any create() call. make() does not require a database connection.
 *
 * @template TModel of Model
 *
 * @package EzPhp\Testing
 */
final readonly class ModelFactory
{
    /**
     * ModelFactory Constructor
     *
     * @param class-string<TModel>               $modelClass
     * @param array<string, mixed>               $defaults   Default attribute values; scalar or callable.
     */
    public function __construct(
        private string $modelClass,
        private array $defaults = [],
    ) {
    }

    /**
     * Build a model instance without persisting it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return TModel
     */
    public function make(array $overrides = []): Model
    {
        $attributes = $this->resolveAttributes($overrides);

        return new $this->modelClass($attributes);
    }

    /**
     * Build and persist a model instance via Model::save().
     *
     * Requires a database to be registered for the model class.
     *
     * @param array<string, mixed> $overrides
     *
     * @return TModel
     */
    public function create(array $overrides = []): Model
    {
        $model = $this->make($overrides);
        $model->save();

        return $model;
    }

    /**
     * Build multiple model instances without persisting them.
     *
     * @param int                  $count
     * @param array<string, mixed> $overrides
     *
     * @return list<TModel>
     */
    public function makeMany(int $count, array $overrides = []): array
    {
        $models = [];

        for ($i = 0; $i < $count; $i++) {
            $models[] = $this->make($overrides);
        }

        return $models;
    }

    /**
     * Build and persist multiple model instances.
     *
     * @param int                  $count
     * @param array<string, mixed> $overrides
     *
     * @return list<TModel>
     */
    public function createMany(int $count, array $overrides = []): array
    {
        $models = [];

        for ($i = 0; $i < $count; $i++) {
            $models[] = $this->create($overrides);
        }

        return $models;
    }

    /**
     * Resolve defaults by invoking any callable values, then merge overrides.
     *
     * Overrides always take precedence over defaults. Callables in defaults are
     * called once per invocation of resolveAttributes(), so each model instance
     * receives an independent evaluation of any dynamic defaults.
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
