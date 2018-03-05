<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Expressive\Router\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Route;
use Zend\Expressive\Router\RouteResult;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

class ImplicitOptionsMiddlewareTest extends TestCase
{
    public function testNonOptionsRequestInvokesHandler()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_GET);
        $request->getAttribute(RouteResult::class, false)->shouldNotBeCalled();

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertSame($response, $result);
    }

    public function testMissingRouteResultInvokesHandler()
    {
        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->willReturn(false);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertSame($response, $result);
    }

    public function testMissingRouteInRouteResultInvokesHandler()
    {
        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([]);
        $result->getMatchedRoute()->willReturn(false);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertSame($response, $result);
    }

    public function testOptionsRequestWhenRouteDefinesOptionsInvokesHandler()
    {
        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(false);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn([RequestMethod::METHOD_OPTIONS]);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->willReturn($response);

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertSame($response, $result);
    }

    public function testWhenNoResponseProvidedToConstructorImplicitOptionsRequestCreatesResponse()
    {
        $allowedMethods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];

        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn($allowedMethods);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->shouldNotBeCalled();

        $middleware = new ImplicitOptionsMiddleware();
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertInstanceOf(Response::class, $result);
        $this->assertNotSame($response, $result);
        $this->assertEquals(StatusCode::STATUS_OK, $result->getStatusCode());
        $this->assertEquals(implode(',', $allowedMethods), $result->getHeaderLine('Allow'));
    }

    public function testInjectsAllowHeaderInResponseProvidedToConstructorDuringOptionsRequest()
    {
        $allowedMethods = [RequestMethod::METHOD_GET, RequestMethod::METHOD_POST];

        $route = $this->prophesize(Route::class);
        $route->implicitOptions()->willReturn(true);

        $result = $this->prophesize(RouteResult::class);
        $result->getAllowedMethods()->willReturn($allowedMethods);
        $result->getMatchedRoute()->will([$route, 'reveal']);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getMethod()->willReturn(RequestMethod::METHOD_OPTIONS);
        $request->getAttribute(RouteResult::class, false)->will([$result, 'reveal']);

        $handler = $this->prophesize(HandlerInterface::class);
        $handler->{HANDLER_METHOD}($request->reveal())->shouldNotBeCalled();

        $expected = $this->prophesize(ResponseInterface::class);
        $expected->withHeader('Allow', implode(',', $allowedMethods))->will([$expected, 'reveal']);

        $middleware = new ImplicitOptionsMiddleware($expected->reveal());
        $result = $middleware->process($request->reveal(), $handler->reveal());
        $this->assertSame($expected->reveal(), $result);
    }
}
