<?php
### Auto-generated from docs/config.schema.yaml — do not edit directly. Run `task build:templates` to regenerate.

namespace App\Service;

use Symfony\Component\Config\Definition\ConfigurationInterface;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
class InvrtConfiguration implements ConfigurationInterface
{
    /** Hard-coded defaults — applied when key is absent from all config sections. */
    public const DEFAULTS = [
{{#each configKeys}}
        '{{name}}' => {{phpDefault}},
{{/each}}
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('invrt');
        $root = $tb->getRootNode();

        $root
            ->children()
                ->scalarNode('name')->defaultNull()->end()
{{#each sections}}
                ->arrayNode('{{name}}')
{{#if addDefaultsIfNotSet}}
                ->addDefaultsIfNotSet()
{{/if}}
{{#if useAttributeAsKey}}
                ->arrayPrototype()
{{/if}}
                ->children()
{{#each extraKeys}}
                    ->scalarNode('{{name}}')->end()
{{/each}}
{{#each keys}}
                    ->{{nodeType}}Node('{{name}}'){{#if showDefault}}->defaultValue({{phpDefault}}){{/if}}->end()
{{/each}}
                    ->end()
                ->end()
{{#if useAttributeAsKey}}
            ->end()
{{/if}}
{{/each}}
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
