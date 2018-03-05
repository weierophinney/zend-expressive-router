<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class ImplicitHeadMiddlewareTest extends TestCase
{
    public function testReturnsResultOfNextOnNonHeadRequests()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfNextWhenNoRouteResultPresentInRequest()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->willReturn(false);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfHandlerWhenRouteResultDoesNotComposeRoute()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([]);
        $result->getMatchedRoute()->willReturn(false);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsResultOfHandlerWhenRouteSupportsHeadExplicitly()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([RequestMethod::METHOD_HEAD]);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())
            ->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertSame($response, $result);
    }

    public function testReturnsNewResponseWhenRouteImplicitlySupportsHeadAndDoesNotSupportGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([RequestMethod::METHOD_HEAD]);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->shouldNotBeCalled($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertInstanceOf(Response::class, $result);
        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals('', (string) $result->getBody());
    }

    public function testReturnsComposedResponseWhenPresentWhenRouteImplicitlySupportsHeadAndDoesNotSupportGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([RequestMethod::METHOD_HEAD]);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_HEAD);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->shouldNotBeCalled($response);

        $expected   = new Response();
        $middleware = new ImplicitHeadMiddleware($expected);
        $result     = $middleware->process($request->reveal(), $handler->reveal());

        $this->assertSame($expected, $result);
    }

    public function testInvokesHandlerWhenRouteImplicitlySupportsHeadAndSupportsGet()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitHead()->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([RequestMethod::METHOD_HEAD, RequestMethod::METHOD_GET]);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = (new ServerRequest([], [], null, RequestMethod::METHOD_HEAD))
                ->withAttribute(RouteResult::class, $result->reveal());

        $response = new Response\JsonResponse(['some_data' => true], 400);

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}(Argument::that(function (ServerRequestInterface $request) {
            $attr = $request->getAttribute(ImplicitHeadMiddleware::FORWARDED_HTTP_METHOD_ATTRIBUTE);
            $this->assertSame('HEAD', $attr);
            return true;
        }))->willReturn($response);

        $middleware = new ImplicitHeadMiddleware();
        $result = $middleware->process($request, $handler->reveal());

        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('', (string) $result->getBody());
        $this->assertSame('application/json', $result->getHeaderLine('content-type'));
    }

    public function testAllowsSpecifyingACustomStreamFactory()
    {
        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $streamFactory = function () {
        };

        $middleware = new ImplicitHeadMiddleware($response, $streamFactory);
        $this->assertAttributeSame($response, 'response', $middleware);
        $this->assertAttributeSame($streamFactory, 'streamFactory', $middleware);
    }
}
