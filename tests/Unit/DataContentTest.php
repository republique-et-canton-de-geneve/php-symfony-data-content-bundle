<?php

namespace EtatGeneve\DatatContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\DataContentException;
use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DataContentTest extends TestCase
{
    protected DataContent $dataContent;
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

        $security->method('getUser')->willReturn(null);
        $this->responseContent = '';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getContent')->willReturnCallback(
            fn (): string => $this->responseContent
        );

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $this->dataContent = new DataContent(
            $httpClient,
            $logger,
            $security,
            $tokenAuthenticator,
            $config
        );
    }

    public function testGetBase(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->getBase();
    }

    public function testSearchByQuery(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->searchByQuery('test-query', [], 10);
        $this->dataContent->searchByQuery('test-query', ['searchLimit' => 100], 10);
    }

    public function testSearchByUuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->searchByUuid('test-uuid');
    }

    public function testGetDocument(): void
    {
        $this->responseContent = 'data';
        $result = $this->dataContent->getDocument('test-uuid', false, false);
        $this->assertEquals('data', $result);

        $this->responseContent = json_encode(['filename' => 'filename', 'extension' => 'txt']);
        $result = $this->dataContent->getDocument('test-uuid', true, false);
        $this->assertInstanceOf(Response::class, $result);

        $this->responseContent = 'data';
        $this->expectException(DataContentException::class);
        $this->dataContent->getDocument('test-uuid', true, false);
    }

    public function testStoreDocument(): void
    {
        $path = realpath(__DIR__ . '/../Fixtures/test-file.txt');
        $this->assertIsString($path);
        $this->dataContent->storeDocument($path, null, ['crtiterions' => 'value1']);
    }

    public function testDeleteDocument(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->deleteDocument('test-uuid');
    }
}
