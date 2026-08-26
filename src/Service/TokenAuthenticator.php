<?php

namespace EtatGeneve\DataContentBundle\Service;

use EtatGeneve\DataContentBundle\Exception\DataContentAuthenticationException;
use EtatGeneve\DataContentBundle\Exception\DataContentConfigException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function hash;
use function intval;
use function is_numeric;
use function is_object;
use function is_string;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 *
 * @phpstan-type DataContentAuthenticatorConfig array{ checkSSL: bool, applicationId: string, clientId: string,
 * clientSecret: string, username : string, password : string, tokenTimeout? : int, tokenAuthSsoUrl : string,
 * audience : string }
 */
class TokenAuthenticator implements InterfaceTokenAuthenticator
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    /** @var DataContentAuthenticatorConfig */
    private array $config;

    private string $keyCache;

    /**
     * @param DataContentConfig $config
     */
    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        CacheInterface $cache,
        array $config,
    ) {
        if (!isset($config['clientId'],$config['clientSecret'],$config['username'],
            $config['password'],$config['audience'],$config['tokenAuthSsoUrl'])) {
            throw new DataContentConfigException('clientId, clientSecret, username, password or audience config parameters are not defined for TokenAuthenticator');
        }
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->config = $config;
        $this->keyCache = 'DataContent-' . hash('sha256', $config['clientId'] . $config['username'] . $config['audience'] . $config['tokenAuthSsoUrl']);
    }

    public function reset(): void
    {
        $this->logger->debug('DataContent : Clear cache token');
        $this->cache->delete($this->keyCache);
    }

    /**
     * Return a sso token, use symfony system cache.
     */
    public function getToken(): string
    {
        $token = $this->cache->get(
            $this->keyCache,
            function (ItemInterface $item): mixed {
                try {
                    $this->logger->debug('DataContent : get token');
                    $parameters = [
                        'verify_host' => $this->config['checkSSL'],
                        'verify_peer' => $this->config['checkSSL'],
                        'headers' => ['X-Application-ID' => $this->config['applicationId']],
                        'body' => [
                            'client_id' => $this->config['clientId'],
                            'client_secret' => $this->config['clientSecret'],
                            'grant_type' => 'password',
                            'username' => $this->config['username'],
                            'password' => $this->config['password'],
                            'audience' => $this->config['audience'],
                        ],
                        'timeout' => $this->config['tokenTimeout'] ?? 15,
                        'max_duration' => $this->config['tokenTimeout'] ?? 15,
                    ];

                    $response = $this->httpClient->request('POST', $this->config['tokenAuthSsoUrl'], $parameters);
                    $data = json_decode($response->getContent());
                    if (
                        is_object($data) && ($data->id_token ?? false)
                        && isset($data->expires_in) && is_numeric($data->expires_in)
                    ) {
                        $ttl = max(1, intval($data->expires_in) - 10);
                        $item->expiresAfter($ttl);

                        return $data->id_token;
                    }
                    $this->logger->error(
                        'DataContent : SSO token response is missing id_token/expires_in'
                    );
                } catch (Throwable $e) {
                    $this->logger->error(
                        'DataContent : SSO token request failed',
                        ['exception' => $e]
                    );
                    $this->reset();

                    throw new DataContentAuthenticationException('DataContent : Invalid SSO token response', 0, $e);
                }
                $this->reset();
                throw new DataContentAuthenticationException('DataContent : Invalid SSO token response');
            },
            0.1
        );

        return is_string($token) ? $token : '';
    }
}
