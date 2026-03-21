<?php

declare(strict_types=1);

namespace EzPhp\Testing;

use EzPhp\Http\Response;
use PHPUnit\Framework\Assert;

/**
 * Wraps a Response with PHPUnit assertion helpers.
 *
 * Returned by the HTTP helpers in HttpTestCase. Provides fluent assertions
 * for common response properties — status code, body content, headers, and
 * JSON payloads.
 *
 * @package EzPhp\Testing
 */
final readonly class TestResponse
{
    /**
     * TestResponse Constructor
     *
     * @param Response $response
     */
    public function __construct(private Response $response)
    {
    }

    /**
     * Return the HTTP status code.
     *
     * @return int
     */
    public function status(): int
    {
        return $this->response->status();
    }

    /**
     * Return the response body.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->response->body();
    }

    /**
     * Return all response headers.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->response->headers();
    }

    /**
     * Assert the response status code equals the given value.
     *
     * @param int $status
     *
     * @return $this
     */
    public function assertStatus(int $status): static
    {
        Assert::assertSame(
            $status,
            $this->response->status(),
            sprintf('Expected status %d but got %d.', $status, $this->response->status()),
        );

        return $this;
    }

    /**
     * Assert the response status is 200 OK.
     *
     * @return $this
     */
    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    /**
     * Assert the response status is 404 Not Found.
     *
     * @return $this
     */
    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    /**
     * Assert the response is a redirect (3xx status code).
     *
     * If $location is given, also asserts the Location header matches exactly.
     *
     * @param string|null $location
     *
     * @return $this
     */
    public function assertRedirect(?string $location = null): static
    {
        $status = $this->response->status();

        Assert::assertTrue(
            $status >= 300 && $status < 400,
            sprintf('Expected a redirect (3xx) status but got %d.', $status),
        );

        if ($location !== null) {
            $this->assertHeader('Location', $location);
        }

        return $this;
    }

    /**
     * Assert the response body contains the given string.
     *
     * @param string $text
     *
     * @return $this
     */
    public function assertSee(string $text): static
    {
        Assert::assertStringContainsString(
            $text,
            $this->response->body(),
            sprintf('Failed asserting that response body contains "%s".', $text),
        );

        return $this;
    }

    /**
     * Assert the response body, decoded as JSON, equals the expected array.
     *
     * Uses strict (===) comparison via assertSame.
     *
     * @param array<string, mixed> $expected
     *
     * @return $this
     */
    public function assertJson(array $expected): static
    {
        $decoded = json_decode($this->response->body(), true);

        Assert::assertIsArray($decoded, 'Response body is not valid JSON.');
        Assert::assertSame($expected, $decoded);

        return $this;
    }

    /**
     * Assert a response header is present.
     *
     * If $value is given, also asserts the header value matches exactly.
     *
     * @param string      $name
     * @param string|null $value
     *
     * @return $this
     */
    public function assertHeader(string $name, ?string $value = null): static
    {
        $headers = $this->response->headers();

        Assert::assertArrayHasKey(
            $name,
            $headers,
            sprintf('Response does not have header "%s".', $name),
        );

        if ($value !== null) {
            Assert::assertSame(
                $value,
                $headers[$name],
                sprintf('Header "%s" expected "%s" but was "%s".', $name, $value, $headers[$name]),
            );
        }

        return $this;
    }
}
