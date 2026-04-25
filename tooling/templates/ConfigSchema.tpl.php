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
}
