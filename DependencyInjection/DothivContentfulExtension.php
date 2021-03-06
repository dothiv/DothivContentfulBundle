<?php

namespace Dothiv\Bundle\ContentfulBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class DothivContentfulExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);
        $container->setParameter('dothiv_contentful.web_path', $config['web_path']);
        $container->setParameter('dothiv_contentful.local_path', $config['local_path']);
        $container->setParameter('dothiv_contentful.webhook', $config['webhook']);
        $container->setParameter('dothiv_contentful.thumbnails', $config['thumbnails']);
        $container->setParameter('dothiv_contentful.default_locale', $config['default_locale']);
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
        $loader->load('listener.yml');
        $loader->load('persistence.yml');
        $loader->load('controllers.yml');
    }

    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
        $cacheConfig    = array();
        $doctrineConfig = array();

        $cacheConfig['providers']['contentful_api_cache'] = array(
            'namespace'   => 'contentful_api',
            'type'        => 'file_system',
            'file_system' => array(
                'directory' => '%kernel.root_dir%/cache/contentful'
            )
        );
        $cacheConfig['providers']['contentful_lastmodified_cache'] = array(
            'namespace'   => 'contentful_lastmodified_cache',
            'type'        => 'file_system',
            'file_system' => array(
                'directory' => '%kernel.root_dir%/cache/contentful'
            )
        );
        $container->prependExtensionConfig('doctrine_cache', $cacheConfig);

        $doctrineConfig['orm']['mappings']['contentful_bundle'] = array(
            'type'   => 'annotation',
            'alias'  => 'ContentfulBundle',
            'dir'    => __DIR__ . '/../Item',
            'prefix' => 'Dothiv\Bundle\ContentfulBundle\Item'
        );
        $container->prependExtensionConfig('doctrine', $doctrineConfig);
    }
}
