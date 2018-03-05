<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2016-2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router\Middleware;

use Fig\Http\Message\RequestMethodInterface as RequestMethod;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface;
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;
use Zend\Diactoros\Response;
use Zend\Expressive\Router\RouteResult;

use const Webimpress\HttpMiddlewareCompatibility\HANDLER_METHOD;

/**
 * Handle implicit OPTIONS requests.
 *
 * Place this middleware after the routing middleware so that it can handle
 * implicit OPTIONS requests -- requests where OPTIONS is used, but the route
 * does not explicitly handle that request method.
 *
 * When invoked, it will create a response with status code 200 and an Allow
 * header that defines all accepted request methods.
 *
 * You may optionally pass a response prototype to the constructor; when
 * present, that prototype will be used to create a new response with the
 * Allow header.
 *
 * The middleware is only invoked in these specific conditions:
 *
 * - an OPTIONS request
 * - with a `RouteResult` present
 * - where the `RouteResult` contains a `Route` instance
 * - and the `Route` instance defines implicit OPTIONS.
 *
 * In all other circumstances, it will return the result of the delegate.
 */
class ImplicitOptionsMiddleware implements MiddlewareInterface
{
    /**
     * @var null|ResponseInterface
     */
    private $response;

    /**
     * @param null|ResponseInterface $response Response prototype to use for
     *     implicit OPTIONS requests; if not provided a zend-diactoros Response
     *     instance will be created and used.
     */
    public function __construct(ResponseInterface $response = null)
    {
        $this->response = $response;
    }

    /**
     * Handle an implicit OPTIONS request.
     *
     * @param ServerRequestInterface $request
     * @param HandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, HandlerInterface $handler)
    {
        if ($request->getMethod() !== RequestMethod::METHOD_OPTIONS) {
            return $handler->{HANDLER_METHOD}($request);
        }

        /** @var RouteResult $result */
        if (false === ($result = $request->getAttribute(RouteResult::class, false))) {
            return $handler->{HANDLER_METHOD}($request);
        }

        $allowedMethods = $result->getAllowedMethods();

        $route = $result->getMatchedRoute();
        if (! $allowedMethods || ($route && ! $route->implicitOptions())) {
            return $handler->{HANDLER_METHOD}($request);
        }

        return $this->getResponse()->withHeader('Allow', implode(',', $allowedMethods));
    }

    /**
     * Return the response prototype to use for an implicit OPTIONS request.
     *
     * @return ResponseInterface
     */
    private function getResponse()
    {
        return $this->response ?: new Response('php://temp', StatusCode::STATUS_OK);
    }
}
