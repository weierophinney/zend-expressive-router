<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-router for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-router/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Router\Test\TestAsset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Webimpress\HttpMiddlewareCompatibility\HandlerInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;

class ImplicitHeadHandler implements HandlerInterface
{
    /** @var HandlerInterface */
    private $handler;

    public function __construct(HandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request)
    {
        $middleware = new ImplicitHeadMiddleware(
            new Response(),
            function () {
                return new Stream('php://temp', 'rw');
            }
        );

        return $middleware->process($request, $this->handler);
    }

    /**
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request)
    {
        return $this->handle($request);
    }
}
