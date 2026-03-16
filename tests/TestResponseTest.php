<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Http\Response;
use EzPhp\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Tests that TestResponse wraps Response correctly and all assertion helpers
 * either pass silently or throw ExpectationFailedException on mismatch.
 */
#[CoversClass(TestResponse::class)]
final class TestResponseTest extends TestCase
{
    // ─── accessors ────────────────────────────────────────────────────────────

    public function testStatusReturnsHttpStatusCode(): void
    {
        $response = new TestResponse(new Response('', 201));

        $this->assertSame(201, $response->status());
    }

    public function testBodyReturnsResponseBody(): void
    {
        $response = new TestResponse(new Response('hello', 200));

        $this->assertSame('hello', $response->body());
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $inner = (new Response())->withHeader('Content-Type', 'application/json');
        $response = new TestResponse($inner);

        $this->assertSame(['Content-Type' => 'application/json'], $response->headers());
    }

    // ─── assertStatus ─────────────────────────────────────────────────────────

    public function testAssertStatusPassesOnMatch(): void
    {
        $response = new TestResponse(new Response('', 200));

        $response->assertStatus(200);
    }

    public function testAssertStatusFailsOnMismatch(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('', 404));
        $response->assertStatus(200);
    }

    // ─── assertOk ─────────────────────────────────────────────────────────────

    public function testAssertOkPassesFor200(): void
    {
        $response = new TestResponse(new Response('', 200));

        $response->assertOk();
    }

    public function testAssertOkFailsForNon200(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('', 201));
        $response->assertOk();
    }

    // ─── assertNotFound ───────────────────────────────────────────────────────

    public function testAssertNotFoundPassesFor404(): void
    {
        $response = new TestResponse(new Response('', 404));

        $response->assertNotFound();
    }

    public function testAssertNotFoundFailsForNon404(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('', 200));
        $response->assertNotFound();
    }

    // ─── assertRedirect ───────────────────────────────────────────────────────

    public function testAssertRedirectPassesFor3xx(): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $response = new TestResponse(new Response('', $status));
            $response->assertRedirect();
        }
    }

    public function testAssertRedirectFailsFor200(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('', 200));
        $response->assertRedirect();
    }

    public function testAssertRedirectChecksLocationHeaderWhenProvided(): void
    {
        $inner = (new Response('', 302))->withHeader('Location', '/home');
        $response = new TestResponse($inner);

        $response->assertRedirect('/home');
    }

    public function testAssertRedirectFailsOnLocationMismatch(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $inner = (new Response('', 302))->withHeader('Location', '/home');
        $response = new TestResponse($inner);

        $response->assertRedirect('/dashboard');
    }

    // ─── assertSee ────────────────────────────────────────────────────────────

    public function testAssertSeePassesWhenBodyContainsText(): void
    {
        $response = new TestResponse(new Response('Hello World', 200));

        $response->assertSee('Hello');
    }

    public function testAssertSeeFailsWhenBodyDoesNotContainText(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('Hello World', 200));
        $response->assertSee('Goodbye');
    }

    // ─── assertJson ───────────────────────────────────────────────────────────

    public function testAssertJsonPassesOnExactMatch(): void
    {
        $body = json_encode(['key' => 'value', 'count' => 1]);
        $this->assertIsString($body);

        $response = new TestResponse(new Response($body, 200));
        $response->assertJson(['key' => 'value', 'count' => 1]);
    }

    public function testAssertJsonFailsOnInvalidJson(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response('not json', 200));
        $response->assertJson([]);
    }

    public function testAssertJsonFailsOnMismatch(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $body = json_encode(['key' => 'value']);
        $this->assertIsString($body);

        $response = new TestResponse(new Response($body, 200));
        $response->assertJson(['key' => 'other']);
    }

    // ─── assertHeader ─────────────────────────────────────────────────────────

    public function testAssertHeaderPassesWhenHeaderExists(): void
    {
        $inner = (new Response())->withHeader('X-Custom', 'abc');
        $response = new TestResponse($inner);

        $response->assertHeader('X-Custom');
    }

    public function testAssertHeaderPassesWhenHeaderMatchesValue(): void
    {
        $inner = (new Response())->withHeader('Content-Type', 'application/json');
        $response = new TestResponse($inner);

        $response->assertHeader('Content-Type', 'application/json');
    }

    public function testAssertHeaderFailsWhenHeaderMissing(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $response = new TestResponse(new Response());
        $response->assertHeader('X-Missing');
    }

    public function testAssertHeaderFailsOnValueMismatch(): void
    {
        $this->expectException(ExpectationFailedException::class);

        $inner = (new Response())->withHeader('Content-Type', 'text/html');
        $response = new TestResponse($inner);

        $response->assertHeader('Content-Type', 'application/json');
    }

    // ─── fluent chaining ──────────────────────────────────────────────────────

    public function testAssertionsAreChainable(): void
    {
        $body = json_encode(['ok' => true]);
        $this->assertIsString($body);

        $inner = (new Response($body, 200))->withHeader('Content-Type', 'application/json');
        $response = new TestResponse($inner);

        $response
            ->assertOk()
            ->assertSee('"ok"')
            ->assertHeader('Content-Type', 'application/json');
    }
}
