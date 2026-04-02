<?php

namespace EtatGeneve\DatatContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\DataContentBundle;
use EtatGeneve\DataContentBundle\DataContentExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DataContentBundleTest extends TestCase
{
    private DataContentBundle $DataContentBundle;

    public function setUp(): void
    {
        $this->DataContentBundle = new DataContentBundle();
    }

    public function testLoad(): void
    {
        $dataContentExtension = $this->DataContentBundle->getContainerExtension();
        $this->assertInstanceOf(DataContentExtension::class, $dataContentExtension);
        $config = ['data_content' => [
            'tokenAuthenticatorClass' => null,
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
        ]];
        $containerBuilder = new ContainerBuilder();
        $dataContentExtension->load($config, $containerBuilder);
    }

    public function testGetAlias(): void
    {
        /**
         * @var DataContentExtension $containerExtension
         */
        $containerExtension = $this->DataContentBundle->getContainerExtension();
        $this->assertEquals('data_content', $containerExtension->getAlias());
    }
}
