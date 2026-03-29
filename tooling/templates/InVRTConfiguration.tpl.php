<?php

namespace App\Input;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/** Auto-generated from docs/config.schema.yaml — do not edit directly. Run `task build:templates` to regenerate. */
class InvrtConfiguration implements ConfigurationInterface
{
    /** Keys shared across settings/environments/profiles/devices sections. */
    public const CONFIG_KEYS = [
{{#each configKeys}}        '{{name}}',
{{/each}}    ];

    /** Hard-coded defaults — applied when key is absent from all config sections. */
    public const DEFAULTS = [
{{#each configKeys}}        '{{name}}' => {{phpDefault}},
{{/each}}    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('invrt');
        $root = $tb->getRootNode();

        $root
            ->children()
            ->scalarNode('name')->defaultNull()->end()
{{#each sections}}            ->arrayNode('{{name}}')
{{#if addDefaultsIfNotSet}}            ->addDefaultsIfNotSet()
{{/if}}{{#if useAttributeAsKey}}            ->useAttributeAsKey('name')
            ->arrayPrototype()
{{/if}}            ->children()
{{#each extraKeys}}            ->scalarNode('{{name}}')->end()
{{/each}}{{#each keys}}            ->{{nodeType}}Node('{{name}}'){{#if showDefault}}->defaultValue({{phpDefault}}){{/if}}->end()
{{/each}}            ->end()
            ->end()
{{#if useAttributeAsKey}}            ->end()
{{/if}}{{/each}}            ->end();

        return $tb;
    }
}
