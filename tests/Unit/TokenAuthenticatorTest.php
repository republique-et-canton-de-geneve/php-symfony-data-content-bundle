<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\DataContentConfigException;
use EtatGeneve\DataContentBundle\DataContentException;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(TokenAuthenticator::class)]
class TokenAuthenticatorTest extends TestCase
{
    protected CacheInterface $cache;

    /** @var array<string, mixed> */
    protected array $config;
    /** @var mixed */
    protected $httpClientResponse;
    protected TokenAuthenticator $tokenAuthenticator;
    protected TokenAuthenticator $tokenAuthenticatorException;
    protected TokenAuthenticator $tokenAuthenticatorConfigException;

    public function setUp(): void
    {
        $this->config = [
            'tokenAuthenticatorClass' => TokenAuthenticator::class,
            'applicationId' => 'xxapplicationId',
            'tenantId' => 'xxtenantId',
            'checkSSL' => true,
            'clientId' => 'xxclientId',
            'clientSecret' => 'xxclientSecret',
            'username' => 'xxusername',
            'password' => 'xxpassword',
            'tokenTimeout' => 1,
            'tokenAuthSsoUrl' => 'xxtokenAuthSsoUrl',
            'restUrl' => 'xxrestUrl',
            'baseId' => 'xxbaseId',
            'audience' => 'xxaudience',
            'timeout' => 1,
        ];

        $this->httpClientResponse = json_encode([
            'id_token' => 'fake_token',
            'expires_in' => 3600,
        ]);

        $logger = $this->createMock(LoggerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = new FilesystemAdapter();
        $this->cache->delete(TokenAuthenticator::DATA_CONTENT_TOKEN_CACHE_KEY);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturnCallback(
            fn () => $this->httpClientResponse
        );

        $httpClient->method('request')->willReturn($response);
        $this->tokenAuthenticator = new TokenAuthenticator(
            $httpClient,
            $logger,
            $this->cache,
            $this->config
        );

        $httpClientException = $this->createMock(HttpClientInterface::class);
        $httpClientException->method('request')->willThrowException(new Exception('HTTP request failed'));
        $this->tokenAuthenticatorException = new TokenAuthenticator(
            $httpClientException,
            $logger,
            $this->cache,
            $this->config
        );
        $configError = $this->config;
        unset($configError['password']);
        $this->tokenAuthenticatorConfigException = new TokenAuthenticator(
            $httpClient,
            $logger,
            $this->cache,
            $configError
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetToken(): void
    {
        $this->cache->delete(TokenAuthenticator::DATA_CONTENT_TOKEN_CACHE_KEY);
        $token = $this->tokenAuthenticator->getToken();
        $this->assertEquals('fake_token', $token);
        // token get from cache

        $token = $this->tokenAuthenticator->getToken();
        $this->assertEquals('fake_token', $token);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetInvalidToken(): void
    {
        $this->cache->delete(TokenAuthenticator::DATA_CONTENT_TOKEN_CACHE_KEY);
        $this->httpClientResponse = json_encode([
            'id_token' => '',
            'expires_in' => 3600,
        ]);
        $this->expectException(DataContentException::class);
        $this->tokenAuthenticator->getToken();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetErrorToken(): void
    {
        $this->cache->delete(TokenAuthenticator::DATA_CONTENT_TOKEN_CACHE_KEY);
        $this->expectException(DataContentException::class);
        $this->tokenAuthenticatorException->getToken();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetConfigErrorToken(): void
    {
        $this->cache->delete(TokenAuthenticator::DATA_CONTENT_TOKEN_CACHE_KEY);
        $this->expectException(DataContentConfigException::class);
        $this->tokenAuthenticatorConfigException->getToken();
    }
}
