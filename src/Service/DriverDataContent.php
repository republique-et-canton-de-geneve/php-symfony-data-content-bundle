<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Service;

use EtatGeneve\DataContentBundle\DataContentRemoteException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function is_int;
use function is_object;
use function is_string;
use function sprintf;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DriverDataContent
{
    protected HttpClientInterface $httpClient;
    protected LoggerInterface $logger;
    protected Security $security;
    protected TokenAuthenticator $tokenAuthenticator;

    /** @var DataContentConfig */
    protected array $config;

    /**
     * @param DataContentConfig $config
     */
    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        Security $security,
        TokenAuthenticator $tokenAuthenticator,
        array $config,
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->security = $security;
        $this->tokenAuthenticator = $tokenAuthenticator;
        $this->config = $config;
        if (!$config['checkSSL']) {
            $this->logger->warning(
                'DataContent : SSL certificate/host verification is DISABLED (checkSSL=false).'
                . ' This should never be used in production.'
            );
        }
    }

    /**
     * return user identifier (loginname).
     */
    protected function getUserIdentifier(): ?string
    {
        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            return $user->getUserIdentifier();
        }

        return null;
    }

    /**
     * @param string  $type    // 'GET', 'PUT', 'DELETE', ....
     * @param mixed[] $headers
     * @param mixed   $body
     */
    public function command(
        string $type,
        string $command,
        $body = null,
        array $headers = [],
        int $additionalTimeout = 0,
    ): ResponseInterface {
        $headers['X-Application-ID'] = $this->config['applicationId'];
        $headers['X-Tenant-ID'] = $this->config['tenantId'];
        $headers['X-Correlation-ID'] = uniqid();
        $this->logger->debug(
            'DataContent : execute command ',
            [
                'type' => $type,
                'command' => $command,
                'headers' => $headers,
                'additionalTimeout' => $additionalTimeout,
            ]
        );
        $url = $this->config['restUrl'] . $command;
        $username = $this->getUserIdentifier();
        if ($username) {
            $headers['connectedAs'] = $username;
        }

        $options = [
            'headers' => $headers,
            'verify_host' => $this->config['checkSSL'],
            'verify_peer' => $this->config['checkSSL'],
            'auth_bearer' => $this->tokenAuthenticator->getToken(),
            'body' => $body,
            'timeout' => $this->config['timeout'] + $additionalTimeout,
            'max_duration' => $this->config['timeout'] + $additionalTimeout,
        ];
        try {
            $response = $this->httpClient->request($type, $url, $options);
            // Symfony's HttpClient is lazy: the status code is only fetched on first access,
            // which is when transport-level errors (DNS, connect timeout, TLS, ...) surface.
            $status = $response->getStatusCode();
        } catch (Throwable $e) {
            $this->logger->error(
                'DataContent : network error while calling GED',
                ['type' => $type, 'command' => $command, 'exception' => $e]
            );

            throw new DataContentRemoteException(sprintf('DataContent : network error for command %s', $command), 0, $e);
        }
        if (400 <= $status) {
            $this->tokenAuthenticator->reset();
        }

        return $response;
    }

    /**
     * @param string  $type    // 'GET', 'PUT', 'DELETE', ....
     * @param mixed[] $headers
     * @param mixed   $body
     */
    public function commandJsonRsp(
        string $type,
        string $command,
        $body = null,
        array $headers = [],
        int $additionalTimeout = 0,
    ): mixed {
        $response = $this->command($type, $command, $body, $headers, $additionalTimeout);
        $headers = $response->getHeaders(false);
        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        $data = json_decode($content);
        if (400 <= $status) {
            $error = 'DataContent : Error, the response is not a valid json';
            if (
                'application/json' == ($headers['content-type'][0] ?? null) && is_object($data)
                && isset($data->exceptionCode) && isset($data->exceptionMessage)
            ) {
                $code = is_int($data->exceptionCode) ? $data->exceptionCode : 0;
                $message = is_string($data->exceptionMessage) ? $data->exceptionMessage : '';
                $error = sprintf('DataContent : Error for command %s : %d %s', $command, $code, $message);
            }
            $this->logger->error(
                'DataContent : GED returned an error response',
                ['command' => $command, 'status' => $status]
            );

            throw new DataContentRemoteException($error);
        }

        return $data;
    }
}
