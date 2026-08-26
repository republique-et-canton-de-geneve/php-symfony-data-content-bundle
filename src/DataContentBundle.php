<?php

declare(strict_types=1);

namespace EtatGeneve\DataContentBundle;

use EtatGeneve\DataContentBundle\Service\DataContent;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DataContentBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        /**
         * @var ArrayNodeDefinition
         */
        $rootNode = $definition->rootNode();
        $child = $rootNode->children();
        $child->booleanNode('checkSSL')->defaultValue(true)->end();
        $child->scalarNode('applicationId')->isRequired()->cannotBeEmpty()->info('Application Name Id')->end();
        $child->scalarNode('tenantId')->defaultValue('admin')->info('Tenant name Id')->end();
        $child->scalarNode('clientId')->cannotBeEmpty()->info('Client Id for token authentication')->end();
        $child->scalarNode('clientSecret')->cannotBeEmpty()->info('Client secret for token authentication')->end();
        $child->scalarNode('restUrl')->isRequired()->cannotBeEmpty()->info('Rest Url for DataContent')->end();
        $child->scalarNode('baseId')->isRequired()->cannotBeEmpty()->info('Base Id for DataContent')->end();
        $child->scalarNode('timeout')->defaultValue(10)->info('Timeout connection for DataContent')->end();
        $child->scalarNode('username')->info('Username for token authentication')->end();
        $child->scalarNode('password')->info('Password secret for token authentication')->end();
        $child->scalarNode('audience')->info('Audience for token request')->end();
        $child->scalarNode('tokenTimeout')->defaultValue(10)->info('Timeout connection for authentication')->end();
        $child->scalarNode('tokenAuthSsoUrl')->info('Token authentication URL')->end();
    }

    /**
     * @param array<string,string|int|bool|null> $config
     **/
    public function loadExtension(array $config, ContainerConfigurator $containerConfigurator, ContainerBuilder $containerBuilder): void
    {
        $services = $containerConfigurator->services();
        $services
            ->defaults()
            ->autowire()      // Automatically injects dependencies in your services.
            ->autoconfigure();
        $services->set(DataContent::class)
            ->arg('$config', $config);
    }
}
