<?php
### Auto-generated from docs/config.schema.yaml — do not edit directly. Run `task build:templates` to regenerate.

namespace App\Service;

use Symfony\Component\Config\Definition\ConfigurationInterface;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
class InvrtConfiguration implements ConfigurationInterface
{
    /** Hard-coded defaults — applied when key is absent from all config sections. */
    public const DEFAULTS = [
        'url' => '',
        'login_url' => '',
        'username' => '',
        'password' => '',
        'viewport_width' => 1024,
        'viewport_height' => 768,
        'max_crawl_depth' => 3,
        'max_pages' => 100,
        'user_agent' => 'InVRT/1.0',
        'max_concurrent_requests' => 5,
        'data_dir' => '',
        'scripts_dir' => '',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('invrt');
        $root = $tb->getRootNode();

        $root
            ->children()
            ->scalarNode('name')->defaultNull()->end()
            ->arrayNode('settings')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('url')->defaultValue('')->end()
            ->scalarNode('login_url')->defaultValue('')->end()
            ->scalarNode('username')->defaultValue('')->end()
            ->scalarNode('password')->defaultValue('')->end()
            ->integerNode('viewport_width')->defaultValue(1024)->end()
            ->integerNode('viewport_height')->defaultValue(768)->end()
            ->integerNode('max_crawl_depth')->defaultValue(3)->end()
            ->integerNode('max_pages')->defaultValue(100)->end()
            ->scalarNode('user_agent')->defaultValue('InVRT/1.0')->end()
            ->integerNode('max_concurrent_requests')->defaultValue(5)->end()
            ->scalarNode('data_dir')->defaultValue('')->end()
            ->scalarNode('scripts_dir')->defaultValue('')->end()
            ->end()
            ->end()
            ->arrayNode('environments')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('url')->end()
            ->scalarNode('login_url')->end()
            ->scalarNode('username')->end()
            ->scalarNode('password')->end()
            ->integerNode('viewport_width')->end()
            ->integerNode('viewport_height')->end()
            ->integerNode('max_crawl_depth')->end()
            ->integerNode('max_pages')->end()
            ->scalarNode('user_agent')->end()
            ->integerNode('max_concurrent_requests')->end()
            ->scalarNode('data_dir')->end()
            ->scalarNode('scripts_dir')->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('profiles')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('description')->end()
            ->scalarNode('url')->end()
            ->scalarNode('login_url')->end()
            ->scalarNode('username')->end()
            ->scalarNode('password')->end()
            ->integerNode('viewport_width')->end()
            ->integerNode('viewport_height')->end()
            ->integerNode('max_crawl_depth')->end()
            ->integerNode('max_pages')->end()
            ->scalarNode('user_agent')->end()
            ->integerNode('max_concurrent_requests')->end()
            ->scalarNode('data_dir')->end()
            ->scalarNode('scripts_dir')->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('devices')
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('name')->end()
            ->scalarNode('description')->end()
            ->scalarNode('url')->end()
            ->scalarNode('login_url')->end()
            ->scalarNode('username')->end()
            ->scalarNode('password')->end()
            ->integerNode('viewport_width')->end()
            ->integerNode('viewport_height')->end()
            ->integerNode('max_crawl_depth')->end()
            ->integerNode('max_pages')->end()
            ->scalarNode('user_agent')->end()
            ->integerNode('max_concurrent_requests')->end()
            ->scalarNode('data_dir')->end()
            ->scalarNode('scripts_dir')->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $tb;
    }

        public function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public function defaults(): array
    {
        return self::DEFAULTS;
    }
}
