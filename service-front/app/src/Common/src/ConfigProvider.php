<?php

declare(strict_types=1);

namespace Common;

use Aws;
use Http;
use Psr;
use Zend;

/**
 * The configuration provider for the Common module
 *
 * @see https://docs.zendframework.com/zend-component-installer/
 */
class ConfigProvider
{
    /**
     * Returns the configuration array
     *
     * To add a bit of a structure, each section is defined in a separate
     * method which returns an array with its configuration.
     */
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'templates'    => $this->getTemplates(),
            'twig'         => $this->getTwig(),
        ];
    }

    /**
     * Returns the container dependencies
     */
    public function getDependencies() : array
    {
        return [

            'aliases' => [

                Psr\Http\Client\ClientInterface::class => Http\Adapter\Guzzle6\Client::class,
                Zend\Expressive\Session\SessionPersistenceInterface::class => Service\Session\EncryptedCookiePersistence::class,

                // The Session Key Manager to use
                Service\Session\KeyManager\KeyManagerInterface::class => Service\Session\KeyManager\KmsManager::class,
            ],

            'factories'  => [

                // Services
                Service\ApiClient\Client::class => Service\ApiClient\ClientFactory::class,
                Service\Session\EncryptedCookiePersistence::class => Service\Session\EncryptedCookiePersistenceFactory::class,
                Service\Session\KeyManager\KmsManager::class => Service\Session\KeyManager\KmsManagerFactory::class,

                Service\Email\EmailClient::class => Service\Email\EmailClientFactory::class,

                Aws\Sdk::class => Service\Aws\SdkFactory::class,
                Aws\Kms\KmsClient::class => Service\Aws\KmsFactory::class,
                Aws\SecretsManager\SecretsManagerClient::class => Service\Aws\SecretsManagerFactory::class,

                Zend\Expressive\Session\SessionMiddleware::class => Zend\Expressive\Session\SessionMiddlewareFactory::class,
            ],

            'delegators' => [
                Zend\Stratigility\Middleware\ErrorHandler::class => [
                    Service\Log\LogStderrListenerDelegatorFactory::class,
                ],
            ],
        ];
    }

    /**
     * Returns the templates configuration
     */
    public function getTemplates() : array
    {
        return [
            'paths' => [
                'error'    => [__DIR__ . '/../templates/error'],
                'layout'   => [__DIR__ . '/../templates/layout'],
                'partials' => [__DIR__ . '/../templates/partials'],
            ],
        ];
    }

    /**
     * @return array
     */
    public function getTwig() : array
    {
        return [
            'extensions' => [
                View\Twig\OrdinalNumberExtension::class,
                View\Twig\GovUKZendFormErrorsExtension::class,
                View\Twig\GovUKZendFormExtension::class,
            ]
        ];
    }
}