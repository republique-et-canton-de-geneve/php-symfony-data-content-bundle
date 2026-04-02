<?php

namespace EtatGeneve\DataContentBundle\Service;

interface InterfaceTokenAuthenticator
{
    public function getToken(): string;

    public function reset(): void;
}
