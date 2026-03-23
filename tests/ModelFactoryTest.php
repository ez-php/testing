<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\Model;
use EzPhp\Testing\ModelFactory;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests that ModelFactory builds and persists Model instances with the correct
 * attribute resolution, override behaviour, and count.
 */
#[CoversClass(ModelFactory::class)]
final class ModelFactoryTest extends TestCase
{
    private PdoTestDatabase $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new PdoTestDatabase('sqlite::memory:');

        $this->db->getPdo()->exec(
            'CREATE TABLE test_users (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT    NOT NULL DEFAULT \'\',
                email TEXT    NOT NULL DEFAULT \'\'
            )',
        );

        TestUser::setDatabase($this->db);
    }

    protected function tearDown(): void
    {
        TestUser::resetDatabase();

        parent::tearDown();
    }

    // ─── make ─────────────────────────────────────────────────────────────────

    public function testMakeReturnsModelInstance(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $user = $factory->make();

        $this->assertInstanceOf(TestUser::class, $user);
    }

    public function testMakeSetsDefaultAttributes(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $user = $factory->make();

        $this->assertSame('Alice', $user->name);
        $this->assertSame('alice@example.com', $user->email);
    }

    public function testMakeAppliesOverrides(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $user = $factory->make(['name' => 'Bob']);

        $this->assertSame('Bob', $user->name);
        $this->assertSame('alice@example.com', $user->email);
    }

    public function testMakeDoesNotPersistModel(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'a@example.com']);

        $factory->make();

        $rows = $this->db->query('SELECT * FROM test_users');
        $this->assertCount(0, $rows);
    }

    // ─── callable defaults ────────────────────────────────────────────────────

    public function testCallableDefaultIsInvokedPerInstance(): void
    {
        $counter = 0;
        $factory = new ModelFactory(TestUser::class, [
            'name' => function () use (&$counter): string {
                $counter++;

                return 'User' . $counter;
            },
            'email' => 'x@example.com',
        ]);

        $first = $factory->make();
        $second = $factory->make();

        $this->assertSame('User1', $first->name);
        $this->assertSame('User2', $second->name);
    }

    // ─── create ───────────────────────────────────────────────────────────────

    public function testCreatePersistsModel(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'a@example.com']);

        $factory->create();

        $rows = $this->db->query('SELECT * FROM test_users');
        $this->assertCount(1, $rows);
    }

    public function testCreateReturnsPersistedModel(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'a@example.com']);

        $user = $factory->create();

        $this->assertNotNull($user->id);
    }

    public function testCreateAppliesOverrides(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Alice', 'email' => 'a@example.com']);

        $user = $factory->create(['name' => 'Charlie']);

        $this->assertSame('Charlie', $user->name);
    }

    // ─── makeMany ─────────────────────────────────────────────────────────────

    public function testMakeManyReturnsCorrectCount(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'User', 'email' => 'u@example.com']);

        $users = $factory->makeMany(3);

        $this->assertCount(3, $users);
    }

    public function testMakeManyDoesNotPersist(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'User', 'email' => 'u@example.com']);

        $factory->makeMany(3);

        $rows = $this->db->query('SELECT * FROM test_users');
        $this->assertCount(0, $rows);
    }

    // ─── createMany ───────────────────────────────────────────────────────────

    public function testCreateManyReturnsCorrectCount(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'User', 'email' => 'u@example.com']);

        $users = $factory->createMany(4);

        $this->assertCount(4, $users);
    }

    public function testCreateManyPersistsAllInstances(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'User', 'email' => 'u@example.com']);

        $factory->createMany(4);

        $rows = $this->db->query('SELECT * FROM test_users');
        $this->assertCount(4, $rows);
    }

    public function testCreateManyAppliesOverridesToEachInstance(): void
    {
        $factory = new ModelFactory(TestUser::class, ['name' => 'Default', 'email' => 'u@example.com']);

        $users = $factory->createMany(2, ['name' => 'Override']);

        foreach ($users as $user) {
            $this->assertSame('Override', $user->name);
        }
    }
}

/**
 * Minimal User model for factory tests.
 *
 * @property int|null $id
 * @property string   $name
 * @property string   $email
 */
final class TestUser extends Model
{
    protected static string $table = 'test_users';

    /**
     * @var list<string>
     */
    protected static array $fillable = ['name', 'email'];
}
