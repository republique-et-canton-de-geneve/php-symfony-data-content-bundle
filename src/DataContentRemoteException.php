<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle;

/**
 * Thrown when the GED REST API returns an error response (HTTP >= 400)
 * that is not an authentication or not-found error.
 */
class DataContentRemoteException extends DataContentException
{
}