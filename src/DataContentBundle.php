<?php

namespace EtatGeneve\DataContentBundle;

use EtatGeneve\DataContentBundle\Service\DataContent;
use EtatGeneve\DataContentBundle\Service\TokenAuthenticator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @phpstan-import-type DataContentConfig from DataContent
 */
class DataContentBundle extends Bundle
{
    public function getContainerExtension(): ?Extension
    {
        return new DataContentExtension();
    }
}

class DataContentExtension extends Extension implements ConfigurationInterface
{
    public function getAlias(): string
    {
        return 'data_content';
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('data_content');
        $rootNode = $treeBuilder->getRootNode();
        /** @var ArrayNodeDefinition $rootNode */
        $child = $rootNode->children();
        $child->scalarNode('checkSSL')->defaultValue(1)->end();
        $child->scalarNode('applicationId')->isRequired()->cannotBeEmpty()->info('Application Name Id')->end();
        $child->scalarNode('clientId')->cannotBeEmpty()->info('Client Id for token authentification')->end();
        $child->scalarNode('clientSecret')->cannotBeEmpty()->info('Client secret for token authentification')->end();
        $child->scalarNode('restUrl')->isRequired()->cannotBeEmpty()->info('Rest Url for DataContent')->end();
        $child->scalarNode('baseId')->isRequired()->cannotBeEmpty()->info('Base Id for DataContent')->end();
        $child->scalarNode('timeout')->defaultValue(10)->info('Timout conection for DataContent')->end();

        $child->scalarNode('tokenAuthenticatorClass')->info('Service for token authentification')->end();

        $child->scalarNode('username')->info('Username for token authentification')->end();
        $child->scalarNode('password')->info('Password secret for token authentification')->end();
        $child->scalarNode('audience')->info('Audience for token request')->end();
        $child->scalarNode('tokenTimeout')->defaultValue(10)->info('Timout conection for authentification')->end();
        $child->scalarNode('tokenAuthSsoUrl')->info('Timout connection for token authentification')->end();

        return $treeBuilder;
    }

    /**
     * @param array<int, array{'tokenAuthenticatorClass': ?string}> $config
     */
    public function load(array $config, ContainerBuilder $container): void
    {
        $configuration = $this;
        $tokenAuthenticatorClass = $config[0]['tokenAuthenticatorClass'];
        if (!$tokenAuthenticatorClass) {
            $tokenAuthenticatorClass = TokenAuthenticator::class;
            $config = $this->processConfiguration($configuration, $config);
            $authDef = new Definition($tokenAuthenticatorClass);
            $authDef->setArgument('$config', $config);
            $authDef->setAutowired(true);
            $authDef->setAutoconfigured(true);
            $container->setDefinition($tokenAuthenticatorClass, $authDef);
        }
        $dcDef = new Definition(DataContent::class);
        $dcDef->setArgument('$config', $config);
        $dcDef->setAutowired(true);
        $dcDef->setAutoconfigured(true);
        $container->setDefinition(DataContent::class, $dcDef);
    }
}
