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

/** Hard-coded defaults — applied when a key is absent from all config sections. */
class ConfigSchema
{
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
        'crawl_file'                => 'INVRT_CRAWL_DIR/crawled-paths.text',
        'crawl_log'                => 'INVRT_CRAWL_DIR/logs/crawl.log',
        'plan_file'                => 'INVRT_DIRECTORY/plan.yaml',
        'reference_file'                => 'INVRT_CRAWL_DIR/logs/reference.log',
        'test_file'                => 'INVRT_CRAWL_DIR/logs/test.log',
        'session_file'                => 'INVRT_CRAWL_DIR/session.json',
        'playwright_spec_file'                => 'INVRT_CRAWL_DIR/INVRT_DEVICE.spec.ts',
        'playwright_config_file'                => 'INVRT_CRAWL_DIR/playwright.config.ts',
    ];
}
