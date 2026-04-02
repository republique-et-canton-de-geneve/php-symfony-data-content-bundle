<?php

namespace EtatGeneve\DatatContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\DataContentException;
use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\DriverDataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DriverDataContentTest extends TestCase
{
    protected DriverDataContent $driverDataContent;
    protected ?string $userIdentifier;
    protected int $responseStatusCode;
    /** @var array<string, array<int, string>> */
    protected array $responseHeader;
    /** @var string|bool */
    protected $responseContent;

    public function setUp(): void
    {
        $config = [
            'tokenAuthenticatorClass' => TokenAuthenticator::class,
            'applicationId' => 'xxapplicationId',
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
        $logger = $this->createMock(LoggerInterface::class);
        $security = $this->createMock(Security::class);

        $tokenAuthenticator = $this->createMock(TokenAuthenticator::class);
        $tokenAuthenticator->method('getToken')->willReturn('fake_token');

        $this->userIdentifier = 'test_user';
        $user = $this->createMock(User::class);
        $user->method('getUserIdentifier')
            ->willReturnCallback(
                function () {
                    return $this->userIdentifier;
                }
            );
        $security->method('getUser')->willReturnCallback(
            function () use ($user) {
                if (null === $this->userIdentifier) {
                    return null;
                }

                return $user;
            }
        );

        $this->responseStatusCode = 200;
        $this->responseContent = '';
        $this->responseHeader = [];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturnCallback(
            function () {
                return $this->responseStatusCode;
            }
        );
        $response->method('getHeaders')->willReturnCallback(
            function () {
                return $this->responseHeader;
            }
        );
        $response->method('getContent')->willReturnCallback(
            function () {
                return $this->responseContent;
            }
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

    public function testCommandError(): void
    {
        $this->responseStatusCode = 500;
        $response = $this->driverDataContent->command(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

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

    public function testCommandJsonRspError500(): void
    {
        $this->responseContent = json_encode('data');
        $this->responseStatusCode = 500;
        $this->expectException(DataContentException::class);
        $response = $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }

    public function testCommandJsonRspError(): void
    {
        $this->responseHeader['content-type'][0] = 'application/json';
        $this->responseContent = json_encode((object) ['exceptionCode' => 100, 'exceptionMessage' => 'Error message']);
        $this->responseStatusCode = 500;
        $this->expectException(DataContentException::class);
        $this->expectExceptionMessage('DataContent : Error for command /test-command : 100 Error message');
        $response = $this->driverDataContent->commandJsonRsp(
            'GET',
            '/test-command',
            null,
            ['Custom-Header' => 'HeaderValue'],
            10
        );
    }
}
