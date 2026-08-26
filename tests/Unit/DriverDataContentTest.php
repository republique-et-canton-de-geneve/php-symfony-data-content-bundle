<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\Exception\DataContentException;
use EtatGeneve\DataContentBundle\Exception\DataContentJsonException;
use EtatGeneve\DataContentBundle\Exception\DataContentRemoteException;
use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\DriverDataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
#[CoversClass(DriverDataContent::class)]
class DriverDataContentTest extends TestCase
{
    protected DriverDataContent $driverDataContent;
    protected int $responseStatusCode = 200;
    /** @var array<string, array<int, string>> */
    protected array $responseHeader;
    /** @var string|bool */
    protected $responseContent;

    public function setUp(): void
    {
        $config = [
            'applicationId' => 'xxapplicationId',
            'tenantId' => 'xxtenantId',
            'checkSSL' => false,
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
        $logger = $this->createMock(LoggerInterface::class);

        $tokenAuthenticator = $this->createMock(TokenAuthenticator::class);
        $tokenAuthenticator->method('getToken')->willReturn('fake_token');

        $this->responseContent = '';
        $this->responseHeader = [];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturnCallback(
            fn (): int => (500 !== $this->responseStatusCode) ? $this->responseStatusCode : throw new Exception()
        );
        $response->method('getHeaders')->willReturnCallback(
            fn (): array => $this->responseHeader
        );
        $response->method('getContent')->willReturnCallback(
            fn (): string => $this->responseContent
        );

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $this->driverDataContent = new DriverDataContent(
            $httpClient,
            $logger,
            $tokenAuthenticator,
            $config
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommand(): void
    {
        $response = $this->driverDataContent->command(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandError500(): void
    {
        $this->responseStatusCode = 500;
        $this->expectException(DataContentRemoteException::class);
        $response = $this->driverDataContent->command(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandError501(): void
    {
        $this->responseStatusCode = 501;
        $response = $this->driverDataContent->command(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandJsonRsp(): void
    {
        $this->responseContent = json_encode('data');
        $response = $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertEquals('data', $response);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandJsonRspCorrupt(): void
    {
        $this->responseContent = 'xxx';
        $this->expectException(DataContentJsonException::class);
        $response = $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandJsonRspError500(): void
    {
        $this->responseContent = json_encode('data');
        $this->responseStatusCode = 500;
        $this->expectException(DataContentException::class);
        $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommandJsonRspError(): void
    {
        $this->responseHeader['content-type'][0] = 'application/json';
        $this->responseContent = json_encode((object) ['exceptionCode' => 100, 'exceptionMessage' => 'Error message']);
        $this->responseStatusCode = 501;
        $this->expectException(DataContentException::class);
        $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }
}
