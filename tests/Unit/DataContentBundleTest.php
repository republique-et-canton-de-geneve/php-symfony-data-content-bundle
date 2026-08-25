<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle\Tests\Unit;

use EtatGeneve\DataContentBundle\DataContentBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[CoversClass(DataContentBundle::class)]
class DataContentBundleTest extends TestCase
{
    private DataContentBundle $dataContentBundle;

    /**
     * @var array<string,string|int|bool|null>
     */
    private array $config = [
        'tokenAuthenticatorClass' => null,
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

    public function setUp(): void
    {
        $this->dataContentBundle = new DataContentBundle();
    }

    public function testLoadExtension(): void
    {
        $containerBuilder = new ContainerBuilder();
        $phpFileLoader = new PhpFileLoader($containerBuilder, new FileLocator(dirname(__DIR__) . '/Resources/config'));
        $instanceOf = [];
        $containerConfigurator = new ContainerConfigurator($containerBuilder, $phpFileLoader, $instanceOf, 'xx', 'xx');
        $this->dataContentBundle->loadExtension($this->config, $containerConfigurator, $containerBuilder);
        $this->expectNotToPerformAssertions();
    }

    public function testConfigure(): void
    {
        $treeBuilder = new TreeBuilder('data_content');
        $fileLocator = new FileLocator();
        $defintionLoader = new DefinitionFileLoader($treeBuilder, $fileLocator);
        $definition = new DefinitionConfigurator($treeBuilder, $defintionLoader, '', '');
        $this->dataContentBundle->configure($definition);
        $node = $definition->rootNode()->getNode(true);
        $processor = new Processor();
        $processor->process($node, [0 => $this->config]);
        $this->expectNotToPerformAssertions();
    }
}
