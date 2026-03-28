<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
use EzPhp\Testing\EntityFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EntityFactory::class)]
final class EntityFactoryTest extends TestCase
{
    private PdoTestDatabase $db;

    /** @var TestPersonRepository */
    private TestPersonRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new PdoTestDatabase('sqlite::memory:');

        $this->db->getPdo()->exec(
            'CREATE TABLE test_persons (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                name  TEXT    NOT NULL DEFAULT \'\',
                email TEXT    NOT NULL DEFAULT \'\'
            )',
        );

        $this->repo = new TestPersonRepository($this->db, new Hydrator());
    }

    // ─── make() ──────────────────────────────────────────────────────────────

    public function testMakeReturnsEntityInstance(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $person = $factory->make();

        self::assertInstanceOf(TestPerson::class, $person);
    }

    public function testMakeSetsDefaultAttributes(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $person = $factory->make();

        self::assertSame('Alice', $person->getAttribute('name'));
        self::assertSame('alice@example.com', $person->getAttribute('email'));
    }

    public function testMakeAppliesOverrides(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'alice@example.com']);

        $person = $factory->make(['name' => 'Bob']);

        self::assertSame('Bob', $person->getAttribute('name'));
        self::assertSame('alice@example.com', $person->getAttribute('email'));
    }

    public function testMakeDoesNotPersistEntity(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'a@example.com']);

        $factory->make();

        $rows = $this->db->query('SELECT * FROM test_persons');
        self::assertCount(0, $rows);
    }

    // ─── callable defaults ────────────────────────────────────────────────────

    public function testCallableDefaultIsInvokedPerInstance(): void
    {
        $counter = 0;
        $factory = new EntityFactory(TestPerson::class, $this->repo, [
            'name' => function () use (&$counter): string {
                $counter++;

                return 'Person' . $counter;
            },
            'email' => 'x@example.com',
        ]);

        $first = $factory->make();
        $second = $factory->make();

        self::assertSame('Person1', $first->getAttribute('name'));
        self::assertSame('Person2', $second->getAttribute('name'));
    }

    // ─── create() ────────────────────────────────────────────────────────────

    public function testCreatePersistsEntity(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'a@example.com']);

        $factory->create();

        $rows = $this->db->query('SELECT * FROM test_persons');
        self::assertCount(1, $rows);
    }

    public function testCreateReturnsPersistedEntity(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'a@example.com']);

        $person = $factory->create();

        self::assertNotNull($person->getAttribute('id'));
    }

    public function testCreateAppliesOverrides(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Alice', 'email' => 'a@example.com']);

        $person = $factory->create(['name' => 'Charlie']);

        self::assertSame('Charlie', $person->getAttribute('name'));
    }

    // ─── makeMany() ──────────────────────────────────────────────────────────

    public function testMakeManyReturnsCorrectCount(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Person', 'email' => 'p@example.com']);

        $persons = $factory->makeMany(3);

        self::assertCount(3, $persons);
    }

    public function testMakeManyDoesNotPersist(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Person', 'email' => 'p@example.com']);

        $factory->makeMany(3);

        $rows = $this->db->query('SELECT * FROM test_persons');
        self::assertCount(0, $rows);
    }

    // ─── createMany() ────────────────────────────────────────────────────────

    public function testCreateManyReturnsCorrectCount(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Person', 'email' => 'p@example.com']);

        $persons = $factory->createMany(4);

        self::assertCount(4, $persons);
    }

    public function testCreateManyPersistsAllInstances(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Person', 'email' => 'p@example.com']);

        $factory->createMany(4);

        $rows = $this->db->query('SELECT * FROM test_persons');
        self::assertCount(4, $rows);
    }

    public function testCreateManyAppliesOverridesToEachInstance(): void
    {
        $factory = new EntityFactory(TestPerson::class, $this->repo, ['name' => 'Default', 'email' => 'p@example.com']);

        $persons = $factory->createMany(2, ['name' => 'Override']);

        foreach ($persons as $person) {
            self::assertSame('Override', $person->getAttribute('name'));
        }
    }
}

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class TestPerson extends Entity
{
    protected static string $table = 'test_persons';

    /** @var list<string> */
    protected static array $fillable = ['name', 'email'];
}

/** @extends AbstractRepository<TestPerson> */
final class TestPersonRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return TestPerson::class;
    }
}
