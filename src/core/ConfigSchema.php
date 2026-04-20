<?php
/**
 * Auto generated. Do not edit.
 * 
 * See 
 *  - @tooling/templates/ConfigSchema.tpl.php
 *  - @docs/spec/Application.yaml
 * and run 
 *  `task build:templates`
 * to regenerate.
 */

namespace InVRT\Core;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** Config schema and hard-coded defaults. Auto-generated from docs/config.schema.yaml — do not edit directly. */
class ConfigSchema implements ConfigurationInterface
{
    /** Hard-coded defaults — applied when a key is absent from all config sections. */
    public const DEFAULTS = [
        'url'                => '',
        'login_url'                => '',
        'username'                => '',
        'password'                => '',
        'cookie'                => '',
        'max_crawl_depth'                => 3,
        'max_pages'                => 100,
        'user_agent'                => 'InVRT/1.0',
        'viewport_width'                => 1024,
        'viewport_height'                => 768,
        'cookies_file'                => 'INVRT_CRAWL_DIR/cookies',
        'exclude_file'                => 'INVRT_CRAWL_DIR/exclude_paths.txt',
        'config_file'                => 'INVRT_DIRECTORY/config.yaml',
        'crawl_log'                => 'INVRT_CRAWL_DIR/logs/crawl.log',
        'directory'                => 'INVRT_CWD/.invrt',
        'scripts_dir'                => 'INVRT_DIRECTORY/scripts',
        'data_dir'                => 'INVRT_DIRECTORY/data',
        'init_file'           => 'INVRT_DIRECTORY/config.yaml',
        'check_file'           => 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/check.yaml',
        'crawl_dir'                => 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE',
        'clone_dir'                => 'INVRT_CRAWL_DIR/clone',
        'crawl_file'           => 'INVRT_CRAWL_DIR/crawled_urls.txt',
        'backstop_config_file'           => 'INVRT_CRAWL_DIR/backstop_config.json',
        'capture_dir'                => 'INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE',
        'reference_file'           => 'INVRT_CAPTURE_DIR/reference_results.txt',
        'test_file'           => 'INVRT_CAPTURE_DIR/test_results.txt',
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
                    ->integerNode('viewport_width')->defaultValue(1024)->end()
                    ->integerNode('viewport_height')->defaultValue(768)->end()
                    ->scalarNode('cookies_file')->defaultValue('INVRT_CRAWL_DIR/cookies')->end()
                    ->scalarNode('exclude_file')->defaultValue('INVRT_CRAWL_DIR/exclude_paths.txt')->end()
                    ->scalarNode('config_file')->defaultValue('INVRT_DIRECTORY/config.yaml')->end()
                    ->scalarNode('crawl_log')->defaultValue('INVRT_CRAWL_DIR/logs/crawl.log')->end()
                    ->scalarNode('directory')->defaultValue('INVRT_CWD/.invrt')->end()
                    ->scalarNode('scripts_dir')->defaultValue('INVRT_DIRECTORY/scripts')->end()
                    ->scalarNode('data_dir')->defaultValue('INVRT_DIRECTORY/data')->end()
                    ->scalarNode('init_file')->defaultValue('INVRT_DIRECTORY/config.yaml')->end()
                    ->scalarNode('check_file')->defaultValue('INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/check.yaml')->end()
                    ->scalarNode('crawl_dir')->defaultValue('INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE')->end()
                    ->scalarNode('clone_dir')->defaultValue('INVRT_CRAWL_DIR/clone')->end()
                    ->scalarNode('crawl_file')->defaultValue('INVRT_CRAWL_DIR/crawled_urls.txt')->end()
                    ->scalarNode('backstop_config_file')->defaultValue('INVRT_CRAWL_DIR/backstop_config.json')->end()
                    ->scalarNode('capture_dir')->defaultValue('INVRT_DIRECTORY/data/INVRT_ENVIRONMENT/INVRT_PROFILE/INVRT_DEVICE')->end()
                    ->scalarNode('reference_file')->defaultValue('INVRT_CAPTURE_DIR/reference_results.txt')->end()
                    ->scalarNode('test_file')->defaultValue('INVRT_CAPTURE_DIR/test_results.txt')->end()
                    ->end()
                ->end()
                ->arrayNode('environments')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('url')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('init_file')->end()
                    ->scalarNode('check_file')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('backstop_config_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->scalarNode('reference_file')->end()
                    ->scalarNode('test_file')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('profiles')
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
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('init_file')->end()
                    ->scalarNode('check_file')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('backstop_config_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->scalarNode('reference_file')->end()
                    ->scalarNode('test_file')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('devices')
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
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('cookies_file')->end()
                    ->scalarNode('exclude_file')->end()
                    ->scalarNode('config_file')->end()
                    ->scalarNode('crawl_log')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('init_file')->end()
                    ->scalarNode('check_file')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('crawl_file')->end()
                    ->scalarNode('backstop_config_file')->end()
                    ->scalarNode('capture_dir')->end()
                    ->scalarNode('reference_file')->end()
                    ->scalarNode('test_file')->end()
                    ->end()
                ->end()
            ->end()
            ->end();

        return $tb;
    }
}
