<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Router — exact matches, path parameters, and the not-found path.
 *
 * @package Felkyo\Tests\Unit
 */
final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    private function get(string $path): Response
    {
        return $this->router->dispatch(new Request('GET', $path, [], '127.0.0.1'));
    }

    public function testAnExactRouteRuns(): void
    {
        $this->router->get('/', static fn (): Response => Response::html('home'));

        $this->assertSame('home', $this->get('/')->body());
    }

    public function testAPathParameterIsCaptured(): void
    {
        $this->router->get('/creature/{id}', static function (Request $r, array $params): Response {
            return Response::html('id=' . $params['id']);
        });

        $this->assertSame('id=42', $this->get('/creature/42')->body());
    }

    public function testATrailingSlashStillMatches(): void
    {
        $this->router->get('/browse', static fn (): Response => Response::html('browse'));

        $this->assertSame('browse', $this->get('/browse/')->body());
    }

    public function testAnUnknownRouteIsA404(): void
    {
        $this->assertSame(404, $this->get('/nowhere')->statusCode());
    }

    public function testTheWrongMethodDoesNotMatch(): void
    {
        $this->router->get('/only-get', static fn (): Response => Response::html('ok'));

        $response = $this->router->dispatch(new Request('POST', '/only-get', [], '127.0.0.1'));
        $this->assertSame(404, $response->statusCode());
    }

    public function testTheNotFoundHandlerIsUsedWhenSet(): void
    {
        $this->router->setNotFoundHandler(static fn (): Response => Response::html('themed 404', 404));

        $response = $this->get('/nowhere');
        $this->assertSame(404, $response->statusCode());
        $this->assertSame('themed 404', $response->body());
    }
}
