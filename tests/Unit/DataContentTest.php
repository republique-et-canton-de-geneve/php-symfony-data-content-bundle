<?php

namespace EtatGeneve\DatatContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DataContentTest extends TestCase
{
    protected DataContent $dataContent;


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
        $httpClient = $this->createMock(HttpClientInterface::class);

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
        $this->dataContent->searchByQuery('test-query', [],  10);
    }


    public function testSearchByUuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->searchByUuid('test-uuid');
    }

    public function testGetDocument(): void
    {
        $this->expectNotToPerformAssertions();
        $this->dataContent->getDocument('test-uuid',false,false);
    }

}
