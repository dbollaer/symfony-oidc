<?php

namespace Drenso\OidcBundle\Http;

interface OidcTokenFactoryInterface
{
    public function getAccessToken(): string;
} 