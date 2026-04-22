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
      #{{#each app.Settings}}
      #{{#if (matches this.type "int") }}
        '{{@key}}'                => {{this.default}},
      #{{else}}
        '{{@key}}'                => '{{this.default}}',
      #{{/if}}
      
      #{{/each}}
      #{{#each app.Commands}}
      #{{#each this.directories}}
        '{{@key}}'                => '{{this.default}}',
      #{{/each}}
      #{{/each}}
      #{{#each app.Files}}
        '{{@key}}'                => '{{this.default}}',
      #{{/each}}
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
                  #{{#each app.Settings}}
                  #{{#if (matches this.type "int") }}
                    ->integerNode('{{@key}}')->defaultValue({{this.default}})->end()
                  #{{else}}
                    ->scalarNode('{{@key}}')->defaultValue('{{this.default}}')->end()
                  #{{/if}}
                  #{{/each}}
                  #{{#each app.Commands}}
                  #{{#each this.directories}}
                    ->scalarNode('{{@key}}')->defaultValue('{{this.default}}')->end()
                  #{{/each}}
                  #{{#if this.output_file }}
                    ->scalarNode('{{snake_case @key}}_file')->defaultValue('{{this.output_file}}')->end()
                  #{{/if}}
                  #{{/each}}
                    ->end()
                ->end()
                ->arrayNode('environments')
                ->arrayPrototype()
                ->children()
                  #{{#each app.Settings}}
                  #{{#if (matches this.type "int") }}
                    ->integerNode('{{@key}}')->end()
                  #{{else}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/if}}
                  #{{/each}}
                  #{{#each app.Commands}}
                  #{{#each this.directories}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/each}}
                  #{{#if this.output_file }}
                    ->scalarNode('{{snake_case @key}}_file')->end()
                  #{{/if}}
                  #{{/each}}
                    ->end()
                ->end()
            ->end()
                ->arrayNode('profiles')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                  #{{#each app.Settings}}
                  #{{#if (matches this.type "int") }}
                    ->integerNode('{{@key}}')->end()
                  #{{else}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/if}}
                  #{{/each}}
                  #{{#each app.Commands}}
                  #{{#each this.directories}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/each}}
                  #{{#if this.output_file }}
                    ->scalarNode('{{snake_case @key}}_file')->end()
                  #{{/if}}
                  #{{/each}}
                    ->end()
                ->end()
            ->end()
                ->arrayNode('devices')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->end()
                  #{{#each app.Settings}}
                  #{{#if (matches this.type "int") }}
                    ->integerNode('{{@key}}')->end()
                  #{{else}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/if}}
                  #{{/each}}
                  #{{#each app.Commands}}
                  #{{#each this.directories}}
                    ->scalarNode('{{@key}}')->end()
                  #{{/each}}
                  #{{#if this.output_file }}
                    ->scalarNode('{{snake_case @key}}_file')->end()
                  #{{/if}}
                  #{{/each}}
                    ->end()
                ->end()
            ->end()
            ->end();

        return $tb;
    }
}
