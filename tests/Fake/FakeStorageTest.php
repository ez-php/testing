<?php

declare(strict_types=1);

namespace Tests\Fake;

use EzPhp\Storage\InMemoryDriver;
use EzPhp\Testing\Fake\FakeStorage;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FakeStorage::class)]
final class FakeStorageTest extends TestCase
{
    public function test_it_stores_like_the_in_memory_driver(): void
    {
        $storage = new FakeStorage();

        self::assertTrue($storage->put('a/b.txt', 'hello'));
        self::assertTrue($storage->exists('a/b.txt'));
        self::assertSame('hello', $storage->get('a/b.txt'));
        self::assertSame(['a/b.txt' => 'hello'], $storage->all());
        self::assertTrue($storage->delete('a/b.txt'));
        self::assertFalse($storage->exists('a/b.txt'));
    }

    public function test_streams_round_trip(): void
    {
        $storage = new FakeStorage();
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, 'streamed');
        rewind($stream);

        self::assertTrue($storage->putStream('s.txt', $stream));
        $out = $storage->getStream('s.txt');
        self::assertIsResource($out);
        self::assertSame('streamed', stream_get_contents($out));
    }

    public function test_url_is_delegated(): void
    {
        $driver = new InMemoryDriver();
        $storage = new FakeStorage($driver);

        self::assertSame($driver->url('x.txt'), $storage->url('x.txt'));
    }

    public function test_it_can_wrap_an_existing_driver(): void
    {
        $driver = new InMemoryDriver();
        $driver->put('shared.txt', 'x');

        (new FakeStorage($driver))->assertExists('shared.txt');
        $this->addToAssertionCount(1);
    }

    public function test_assertions_pass_and_fail_correctly(): void
    {
        $storage = new FakeStorage();
        $storage->assertEmpty();
        $storage->assertMissing('nope.txt');

        $storage->put('yes.txt', 'content');
        $storage->assertExists('yes.txt');
        $storage->assertContents('yes.txt', 'content');

        foreach ([
            static fn () => $storage->assertExists('nope.txt'),
            static fn () => $storage->assertMissing('yes.txt'),
            static fn () => $storage->assertContents('yes.txt', 'other'),
            static fn () => $storage->assertContents('nope.txt', 'x'),
            static fn () => $storage->assertEmpty(),
        ] as $assertion) {
            try {
                $assertion();
                self::fail('the assertion should have failed');
            } catch (AssertionFailedError) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
