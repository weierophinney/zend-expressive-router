<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router\Test;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Generator;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Stream;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\PathBasedRoutingMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

/**
 * Base class for testing adapter integrations.
 *
 * Implementers of adapters should extend this class in their test suite,
 * implementing the `getRouter()` method.
 *
 * This test class tests that the router correctly marshals the allowed methods
 * for a match that matches the path, but not the request method.
 */
abstract class ImplicitMethodsIntegrationTest extends TestCase
{
    /**
     * @return RouterInterface
     */
    abstract public function getRouter();

    /**
     * @return Generator
     */
    public function method()
    {
        yield RequestMethod::METHOD_HEAD => [
            RequestMethod::METHOD_HEAD,
            new ImplicitHeadMiddleware(
                function () {
                    return new Response();
                },
                function () {
                    return new Stream('php://temp', 'rw');
                }
            ),
        ];
        yield RequestMethod::METHOD_OPTIONS => [
            RequestMethod::METHOD_OPTIONS,
            new ImplicitOptionsMiddleware(
                function () {
                    return new Response();
                }
            ),
        ];
    }

    /**
     * @dataProvider method
     * @param string $method
     */
    public function testExplicitRequest($method, MiddlewareInterface $middleware)
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $route1 = new Route('/api/v1/me', $middleware1, [$method]);
        $route2 = new Route('/api/v1/me', $middleware2, [RequestMethod::METHOD_GET]);

        $router = $this->getRouter();
        $router->addRoute($route1);
        $router->addRoute($route2);

        $finalResponse = (new Response())->withHeader('foo-bar', 'baz');
        $finalResponse->getBody()->write('FOO BAR BODY');

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler
            ->handle(Argument::that(function (ServerRequestInterface $request) use ($method, $route1) {
                if ($request->getMethod() !== $method) {
                    return false;
                }

                if ($request->getAttribute(ImplicitHeadMiddleware::FORWARDED_HTTP_METHOD_ATTRIBUTE) !== null) {
                    return false;
                }

                $routeResult = $request->getAttribute(RouteResult::class);
                if (! $routeResult) {
                    return false;
                }

                if (! $routeResult->isSuccess()) {
                    return false;
                }

                $matchedRoute = $routeResult->getMatchedRoute();
                if (! $matchedRoute) {
                    return false;
                }

                if ($matchedRoute !== $route1) {
                    return false;
                }

                return true;
            }))
            ->willReturn($finalResponse)
            ->shouldBeCalledTimes(1);

        $routeMiddleware = new PathBasedRoutingMiddleware($router);
        $handler = new TestAsset\PassThroughFinalHandler($finalHandler->reveal(), $middleware);

        $request = new ServerRequest([], [], '/api/v1/me', $method);

        $response = $routeMiddleware->process($request, $handler);

        $this->assertEquals(StatusCode::STATUS_OK, $response->getStatusCode());
        $this->assertSame('FOO BAR BODY', (string) $response->getBody());
        $this->assertTrue($response->hasHeader('foo-bar'));
        $this->assertSame('baz', $response->getHeaderLine('foo-bar'));
    }

    public function testImplicitHeadRequest()
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $route1 = new Route('/api/v1/me', $middleware1, [RequestMethod::METHOD_GET]);
        $route2 = new Route('/api/v1/me', $middleware2, [RequestMethod::METHOD_POST]);

        $router = $this->getRouter();
        $router->addRoute($route1);
        $router->addRoute($route2);

        $finalResponse = (new Response())->withHeader('foo-bar', 'baz');
        $finalResponse->getBody()->write('FOO BAR BODY');

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler
            ->handle(Argument::that(function (ServerRequestInterface $request) use ($route1) {
                if ($request->getMethod() !== RequestMethod::METHOD_GET) {
                    return false;
                }

                if ($request->getAttribute(ImplicitHeadMiddleware::FORWARDED_HTTP_METHOD_ATTRIBUTE)
                    !== RequestMethod::METHOD_HEAD
                ) {
                    return false;
                }

                $routeResult = $request->getAttribute(RouteResult::class);
                if (! $routeResult) {
                    return false;
                }

                if (! $routeResult->isSuccess()) {
                    return false;
                }

                $matchedRoute = $routeResult->getMatchedRoute();
                if (! $matchedRoute) {
                    return false;
                }

                if ($matchedRoute !== $route1) {
                    return false;
                }

                return true;
            }))
            ->willReturn($finalResponse)
            ->shouldBeCalledTimes(1);

        $routeMiddleware = new PathBasedRoutingMiddleware($router);
        $handler = new TestAsset\ImplicitHeadHandler($finalHandler->reveal());
        $request = new ServerRequest([], [], '/api/v1/me', RequestMethod::METHOD_HEAD);

        $response = $routeMiddleware->process($request, $handler);

        $this->assertEquals(StatusCode::STATUS_OK, $response->getStatusCode());
        $this->assertEmpty((string) $response->getBody());
        $this->assertTrue($response->hasHeader('foo-bar'));
        $this->assertSame('baz', $response->getHeaderLine('foo-bar'));
    }

    public function testImplicitOptionsRequest()
    {
        $middleware1 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $middleware2 = $this->prophesize(MiddlewareInterface::class)->reveal();
        $route1 = new Route('/api/v1/me', $middleware1, [RequestMethod::METHOD_GET]);
        $route2 = new Route('/api/v1/me', $middleware2, [RequestMethod::METHOD_POST]);

        $router = $this->getRouter();
        $router->addRoute($route1);
        $router->addRoute($route2);

        $finalHandler = $this->prophesize(RequestHandlerInterface::class);
        $finalHandler->handle()->shouldNotBeCalled();

        $finalResponse = (new Response())->withHeader('foo-bar', 'baz');
        $finalResponse->getBody()->write('response body bar');

        $routeMiddleware = new PathBasedRoutingMiddleware($router);
        $handler = new TestAsset\ImplicitOptionsMiddleware($finalHandler->reveal(), $finalResponse);
        $request = new ServerRequest([], [], '/api/v1/me', RequestMethod::METHOD_OPTIONS);

        $response = $routeMiddleware->process($request, $handler);

        $this->assertSame(StatusCode::STATUS_OK, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Allow'));
        $this->assertSame('GET,POST', $response->getHeaderLine('Allow'));
        $this->assertTrue($response->hasHeader('foo-bar'));
        $this->assertSame('baz', $response->getHeaderLine('foo-bar'));
        $this->assertSame('response body bar', (string) $response->getBody());
    }
}
