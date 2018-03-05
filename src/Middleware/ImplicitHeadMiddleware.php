<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2017-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use Zend\Expressive\Router\RouteResult;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

/**
 * Handle implicit HEAD requests.
 *
 * Place this middleware after the routing middleware so that it can handle
 * implicit HEAD requests -- requests where HEAD is used, but the route does
 * not explicitly handle that request method.
 *
 * When invoked, it will create an empty response with status code 200.
 *
 * You may optionally pass a response prototype to the constructor; when
 * present, that instance will be returned instead.
 *
 * The middleware is only invoked in these specific conditions:
 *
 * - a HEAD request
 * - with a `RouteResult` present
 * - where the `RouteResult` contains a `Route` instance
 * - and the `Route` instance defines implicit HEAD.
 *
 * In all other circumstances, it will return the result of the delegate.
 *
 * If the route instance supports GET requests, the middleware dispatches
 * the next layer, but alters the request passed to use the GET method;
 * it then provides an empty response body to the returned response.
 */
class ImplicitHeadMiddleware implements MiddlewareInterface
{
    const FORWARDED_HTTP_METHOD_ATTRIBUTE = 'forwarded_http_method';

    /**
     * @var null|ResponseInterface
     */
    private $response;

    /**
     * PHP callable capable of producing an empty, writable StreamInterface
     * instance.
     *
     * @var callable
     */
    private $streamFactory;

    /**
     * @param null|ResponseInterface $response Response prototype to return
     *     for implicit HEAD requests; if none provided, an empty zend-diactoros
     *     instance will be created.
     * @param null|callable $streamFactory PHP callable capable of producing an
     *     empty, writable StreamInterface instance. If none is provided, a factory
     *     generating an empty zend-diactoros Stream instance will be created.
     */
    public function __construct(ResponseInterface $response = null, callable $streamFactory = null)
    {
        $this->response = $response;
        $this->streamFactory = $streamFactory ?: function () {
            return new Stream('php://temp', 'wb+');
        };
    }

    /**
     * Handle an implicit HEAD request.
     *
     * If the route allows GET requests, dispatches as a GET request and
     * resets the response body to be empty; otherwise, creates a new empty
     * response.
     *
     * @param ServerRequestInterface $request
     * @param HandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, HandlerInterface $handler)
    {
        if ($request->getMethod() !== RequestMethod::METHOD_HEAD) {
            return $handler->{HANDLER_METHOD}($request);
        }

        /** @var RouteResult $result */
        if (false === ($result = $request->getAttribute(RouteResult::class, false))) {
            return $handler->{HANDLER_METHOD}($request);
        }

        $allowedMethods = $result->getAllowedMethods();

        $route = $result->getMatchedRoute();
        if (! $allowedMethods || ($route && ! $route->implicitHead())) {
            return $handler->{HANDLER_METHOD}($request);
        }

        if (! in_array(RequestMethod::METHOD_GET, $allowedMethods, true)) {
            return $this->getResponse();
        }

        $response = $handler->{HANDLER_METHOD}(
            $request
                ->withMethod(RequestMethod::METHOD_GET)
                ->withAttribute(self::FORWARDED_HTTP_METHOD_ATTRIBUTE, RequestMethod::METHOD_HEAD)
        );

        $streamFactory = $this->streamFactory;
        $body = $streamFactory();
        return $response->withBody($body);
    }

    /**
     * Return the response prototype to use for an implicit HEAD request.
     *
     * @return ResponseInterface
     */
    private function getResponse()
    {
        return $this->response ?: new Response();
    }
}
