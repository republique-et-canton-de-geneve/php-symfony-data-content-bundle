<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Exception;

/**
 * Thrown when SSO/token authentication against the GED fails
 * (network error, malformed response, invalid credentials, expired token refresh, ...).
 */
class DataContentAuthenticationException extends DataContentException
{
}
