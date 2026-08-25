<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\Exception\DataContentException;
use EtatGeneve\DataContentBundle\Exception\DataContentRemoteException;
use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\DriverDataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
#[CoversClass(DriverDataContent::class)]
class DriverDataContentTest extends TestCase
{
    protected DriverDataContent $driverDataContent;
    protected ?string $userIdentifier;
    protected int $responseStatusCode = 200;
    /** @var array<string, array<int, string>> */
    protected array $responseHeader;
    /** @var string|bool */
    protected $responseContent;

    public function setUp(): void
    {
        $config = [
            'tokenAuthenticatorClass' => null,
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
        $security = $this->createMock(Security::class);

        $tokenAuthenticator = $this->createMock(TokenAuthenticator::class);
        $tokenAuthenticator->method('getToken')->willReturn('fake_token');

        $this->userIdentifier = 'test_user';
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')
            ->willReturnCallback(
                fn (): string => $this->userIdentifier
            );
        $security->method('getUser')->willReturnCallback(
            function () use ($user): ?\PHPUnit\Framework\MockObject\MockObject {
                if (null === $this->userIdentifier) {
                    return null;
                }

                return $user;
            }
        );

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
            $security,
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
    public function testCommandNoUser(): void
    {
        $this->userIdentifier = '';
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
    public function testCommandNullUser(): void
    {
        $this->userIdentifier = null;
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
        $response = $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertEquals(null, $response);
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
        $this->expectExceptionMessage('DataContent : Error for command /test-command : 100 Error message');
        $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }
}
