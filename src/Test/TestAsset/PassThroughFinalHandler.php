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
use Webimpress\HttpMiddlewareCompatibility\MiddlewareInterface;

class PassThroughFinalHandler implements HandlerInterface
{
    /** @var HandlerInterface */
    private $handler;

    /** @var MiddlewareInterface */
    private $middleware;

    public function __construct(HandlerInterface $handler, MiddlewareInterface $middleware)
    {
        $this->handler = $handler;
        $this->middleware = $middleware;
    }

    /**
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request)
    {
        return $this->middleware->process($request, $this->handler);
    }

    /**
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request)
    {
        return $this->handle($request);
    }
}
