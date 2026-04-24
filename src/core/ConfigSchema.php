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
        'name'                => '',
        'description'                => '',
        'id'                => '',
        'login_url'                => '',
        'username'                => '',
        'password'                => '',
        'cookie'                => '',
        'max_crawl_depth'                => 3,
        'max_pages'                => 100,
        'user_agent'                => 'InVRT/1.0',
        'viewport_width'                => 1024,
        'viewport_height'                => 768,
        'directory'                => 'INVRT_CWD/.invrt',
        'scripts_dir'                => 'INVRT_DIRECTORY/scripts',
        'data_dir'                => 'INVRT_DIRECTORY/data',
        'crawl_dir'                => 'INVRT_DIRECTORY/data/INVRT_PROFILE',
        'clone_dir'                => 'INVRT_CRAWL_DIR/clone',
        'capture_dir'                => 'INVRT_CRAWL_DIR/bitmaps',
        'config_file'                => 'INVRT_DIRECTORY/config.yaml',
        'crawl_file'                => 'INVRT_CRAWL_DIR/crawled-paths.text',
        'crawl_log'                => 'INVRT_CRAWL_DIR/logs/crawl.log',
        'plan_file'                => 'INVRT_DIRECTORY/plan.yaml',
        'reference_file'                => 'INVRT_CRAWL_DIR/logs/reference.log',
        'test_file'                => 'INVRT_CRAWL_DIR/logs/test.log',
        'session_file'                => 'INVRT_CRAWL_DIR/session.json',
        'playwright_spec_file'                => 'INVRT_CRAWL_DIR/INVRT_DEVICE.spec.ts',
        'playwright_config_file'                => 'INVRT_CRAWL_DIR/playwright.config.ts',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('invrt');
        $root = $tb->getRootNode();

        $root
            ->children()
                ->scalarNode('name')->defaultNull()->end()
                ->scalarNode('id')->defaultNull()->end()
                ->scalarNode('url')->defaultNull()->end()
                ->scalarNode('description')->defaultNull()->end()
                ->arrayNode('project')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('url')->defaultValue('')->end()
                    ->scalarNode('name')->defaultValue('')->end()
                    ->scalarNode('description')->defaultValue('')->end()
                    ->scalarNode('id')->defaultValue('')->end()
                    ->scalarNode('login_url')->defaultValue('')->end()
                    ->scalarNode('username')->defaultValue('')->end()
                    ->scalarNode('password')->defaultValue('')->end()
                    ->scalarNode('cookie')->defaultValue('')->end()
                    ->integerNode('max_crawl_depth')->defaultValue(3)->end()
                    ->integerNode('max_pages')->defaultValue(100)->end()
                    ->scalarNode('user_agent')->defaultValue('InVRT/1.0')->end()
                    ->integerNode('viewport_width')->defaultValue(1024)->end()
                    ->integerNode('viewport_height')->defaultValue(768)->end()
                    ->scalarNode('directory')->defaultValue('INVRT_CWD/.invrt')->end()
                    ->scalarNode('scripts_dir')->defaultValue('INVRT_DIRECTORY/scripts')->end()
                    ->scalarNode('data_dir')->defaultValue('INVRT_DIRECTORY/data')->end()
                    ->scalarNode('crawl_dir')->defaultValue('INVRT_DIRECTORY/data/INVRT_PROFILE')->end()
                    ->scalarNode('clone_dir')->defaultValue('INVRT_CRAWL_DIR/clone')->end()
                    ->scalarNode('capture_dir')->defaultValue('INVRT_CRAWL_DIR/bitmaps')->end()
                    ->end()
                ->end()
                ->arrayNode('environments')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('url')->end()
                    ->scalarNode('name')->end()
                    ->scalarNode('description')->end()
                    ->scalarNode('id')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('profiles')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('name')->end()
                    ->scalarNode('description')->end()
                    ->scalarNode('id')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
                ->arrayNode('devices')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                    ->scalarNode('url')->end()
                    ->scalarNode('name')->end()
                    ->scalarNode('description')->end()
                    ->scalarNode('id')->end()
                    ->scalarNode('login_url')->end()
                    ->scalarNode('username')->end()
                    ->scalarNode('password')->end()
                    ->scalarNode('cookie')->end()
                    ->integerNode('max_crawl_depth')->end()
                    ->integerNode('max_pages')->end()
                    ->scalarNode('user_agent')->end()
                    ->integerNode('viewport_width')->end()
                    ->integerNode('viewport_height')->end()
                    ->scalarNode('directory')->end()
                    ->scalarNode('scripts_dir')->end()
                    ->scalarNode('data_dir')->end()
                    ->scalarNode('crawl_dir')->end()
                    ->scalarNode('clone_dir')->end()
                    ->scalarNode('capture_dir')->end()
                    ->end()
                ->end()
            ->end()
            ->end();

        return $tb;
    }
}
