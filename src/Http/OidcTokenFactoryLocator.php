<?php

namespace Drenso\OidcBundle\Http;

use Psr\Container\ContainerInterface;

class OidcTokenFactoryLocator
{
    public function __construct(
        private ContainerInterface $factories,
        private string $defaultFactory,
    ) {
    }

    public function get(string $name = null): OidcTokenFactoryInterface
    {
        $factoryName = $name ?? $this->defaultFactory;

        if (!$this->factories->has($factoryName)) {
            throw new \InvalidArgumentException(sprintf('Token factory "%s" not found.', $factoryName));
        }

        return $this->factories->get($factoryName);
    }
} 