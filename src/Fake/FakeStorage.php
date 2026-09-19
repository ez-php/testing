<?php

declare(strict_types=1);

namespace EzPhp\Testing\Fake;

use EzPhp\Http\UploadedFile;
use EzPhp\Storage\InMemoryDriver;
use EzPhp\Storage\StorageInterface;
use PHPUnit\Framework\Assert;

/**
 * Class FakeStorage
 *
 * `StorageInterface` for tests: an `ez-php/storage` `InMemoryDriver` (reused, not
 * reimplemented) with assertions on top.
 *
 *     $storage = new FakeStorage();
 *     (new AvatarService($storage))->upload($user, $file);
 *     $storage->assertExists("avatars/{$user->id}.png");
 *
 * `ez-php/storage` is a soft dependency (`suggest`): only autoloaded when used.
 *
 * @package EzPhp\Testing\Fake
 */
final class FakeStorage implements StorageInterface
{
    private readonly InMemoryDriver $driver;

    /**
     * FakeStorage Constructor
     *
     * @param InMemoryDriver|null $driver Use an existing driver (e.g. one already bound in the container).
     */
    public function __construct(?InMemoryDriver $driver = null)
    {
        $this->driver = $driver ?? new InMemoryDriver();
    }

    /**
     * @param string $path
     * @param string $contents
     *
     * @return bool
     */
    public function put(string $path, string $contents): bool
    {
        return $this->driver->put($path, $contents);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function get(string $path): string
    {
        return $this->driver->get($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function delete(string $path): bool
    {
        return $this->driver->delete($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->driver->exists($path);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    public function url(string $path): string
    {
        return $this->driver->url($path);
    }

    /**
     * @param string       $path
     * @param UploadedFile $file
     *
     * @return bool
     */
    public function putUploadedFile(string $path, UploadedFile $file): bool
    {
        return $this->driver->putUploadedFile($path, $file);
    }

    /**
     * @param string $path
     *
     * @return mixed
     */
    public function getStream(string $path): mixed
    {
        return $this->driver->getStream($path);
    }

    /**
     * @param string   $path
     * @param resource $stream
     *
     * @return bool
     */
    public function putStream(string $path, mixed $stream): bool
    {
        return $this->driver->putStream($path, $stream);
    }

    /**
     * Everything stored, path => contents.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->driver->all();
    }

    /**
     * Assert that a file exists at the path.
     *
     * @param string $path
     *
     * @return void
     */
    public function assertExists(string $path): void
    {
        Assert::assertTrue($this->driver->exists($path), "The file [{$path}] does not exist.");
    }

    /**
     * Assert that no file exists at the path.
     *
     * @param string $path
     *
     * @return void
     */
    public function assertMissing(string $path): void
    {
        Assert::assertFalse($this->driver->exists($path), "The file [{$path}] exists but should not.");
    }

    /**
     * Assert that the file at the path has exactly these contents.
     *
     * @param string $path
     * @param string $contents
     *
     * @return void
     */
    public function assertContents(string $path, string $contents): void
    {
        $this->assertExists($path);
        Assert::assertSame($contents, $this->driver->get($path), "The file [{$path}] does not have the expected contents.");
    }

    /**
     * Assert that nothing is stored.
     *
     * @return void
     */
    public function assertEmpty(): void
    {
        Assert::assertSame([], $this->driver->all(), 'Files exist unexpectedly: ' . implode(', ', array_keys($this->driver->all())));
    }
}
