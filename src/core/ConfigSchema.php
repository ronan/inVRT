<?php

namespace InVRT\Core;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** Config schema and hard-coded defaults. Auto-generated from docs/config.schema.yaml — do not edit directly. */
class ConfigSchema implements ConfigurationInterface
{
    /** Hard-coded defaults — applied when a key is absent from all config sections. */
    public const DEFAULTS = [
        'url' => '',
        'login_url' => '',
        'username' => '',
        'password' => '',
        'cookie' => '',
        'max_crawl_depth' => 3,
        'max_pages' => 100,
        'user_agent' => 'InVRT/1.0',
        'max_concurrent_requests' => 5,
        'viewport_width' => 1024,
        'viewport_height' => 768,
        'directory' => 'INVRT_CWD/.invrt',
        'config_file' => 'INVRT_DIRECTORY/config.yaml',
        'scripts_dir' => 'INVRT_DIRECTORY/scripts',
        'crawl_dir' => 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE',
        'cookies_file' => 'INVRT_CRAWL_DIR/cookies',
        'crawl_log' => 'INVRT_CRAWL_DIR/logs/crawl.log',
        'clone_dir' => 'INVRT_CRAWL_DIR/clone',
        'crawl_file' => 'INVRT_CRAWL_DIR/crawled_urls.txt',
        'exclude_file' => 'INVRT_CRAWL_DIR/exclude_paths.txt',
        'capture_dir' => 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE',
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
                    ->scalarNode('cookie')->defaultValue('')->end()
                    ->integerNode('max_crawl_depth')->defaultValue(3)->end()
                    ->integerNode('max_pages')->defaultValue(100)->end()
                    ->scalarNode('user_agent')->defaultValue('InVRT/1.0')->end()
                    ->integerNode('max_concurrent_requests')->defaultValue(5)->end()
                    ->integerNode('viewport_width')->defaultValue(1024)->end()
                    ->integerNode('viewport_height')->defaultValue(768)->end()
                    ->scalarNode('directory')->defaultValue('INVRT_CWD/.invrt')->end()
                    ->scalarNode('config_file')->defaultValue('INVRT_DIRECTORY/config.yaml')->end()
                    ->scalarNode('scripts_dir')->defaultValue('INVRT_DIRECTORY/scripts')->end()
                    ->scalarNode('crawl_dir')->defaultValue('INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE')->end()
                    ->scalarNode('cookies_file')->defaultValue('INVRT_CRAWL_DIR/cookies')->end()
                    ->scalarNode('crawl_log')->defaultValue('INVRT_CRAWL_DIR/logs/crawl.log')->end()
                    ->scalarNode('clone_dir')->defaultValue('INVRT_CRAWL_DIR/clone')->end()
                    ->scalarNode('crawl_file')->defaultValue('INVRT_CRAWL_DIR/crawled_urls.txt')->end()
                    ->scalarNode('exclude_file')->defaultValue('INVRT_CRAWL_DIR/exclude_paths.txt')->end()
                    ->scalarNode('capture_dir')->defaultValue('INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE')->end()
                    ->end()
                ->end()
                ->arrayNode('environments')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('max_concurrent_requests')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('profiles')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('description')->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('max_concurrent_requests')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('devices')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('description')->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('max_concurrent_requests')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
            ->end();

        return $tb;
    }
}
