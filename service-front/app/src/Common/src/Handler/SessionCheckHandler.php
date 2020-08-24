<?php

declare(strict_types=1);

namespace Common\Handler;

use Common\Handler\Traits\Session as SessionTrait;
use Common\Service\Session\EncryptedCookiePersistence;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Exception\RuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionCheckHandler implements RequestHandlerInterface
{
    use SessionTrait;

    private ContainerInterface $container;

    /**
     * SessionCheckHandler constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $this->getSession($request, 'session');
        $config = $this->container->get('config');

        if (!isset($config['session']['expires'])) {
            throw new RuntimeException('Missing session expiry value');
        }

        $expiresAt = $session->get(EncryptedCookiePersistence::SESSION_TIME_KEY) + $config['session']['expires'];
        $timeRemaining = $expiresAt - time();

        // Do we have 5min remaining
        $sessionWarning = $timeRemaining <= 300;

        return new JsonResponse(
            [
                'session_warning' => $sessionWarning,
                'time_remaining'  => $timeRemaining
            ],
            201
        );
    }
}
