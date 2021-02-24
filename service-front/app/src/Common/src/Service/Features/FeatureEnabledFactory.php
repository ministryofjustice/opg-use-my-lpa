<?php

declare(strict_types=1);

namespace Common\Service\Features;

use Psr\Container\ContainerInterface;

class FeatureEnabledFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');

        if (!isset($config['feature_flags']) && !is_array($config['feature_flags'])) {
            throw new \UnexpectedValueException('Missing feature flags configuration');
        }

        return new FeatureEnabled(
            $config['feature_flags']
        );
    }
}
