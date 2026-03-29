#!/usr/bin/env node

/**
 * Generates src/Input/InvrtConfiguration.php from docs/config.schema.yaml
 * using the Handlebars template at src/Input/InVRTConfiguration.tpl.php.
 */

import { readFileSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import yaml from 'js-yaml';
import Handlebars from 'handlebars';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '../..');

const schema = yaml.load(readFileSync(resolve(root, 'docs/config.schema.yaml'), 'utf8'));

// Build configKeys from $defs.configKeys with pre-computed template values.
const configKeysDef = schema.$defs.configKeys.properties;
const configKeys = Object.entries(configKeysDef).map(([name, def]) => {
    const type = def.type === 'integer' ? 'integer' : 'string';
    const raw = 'default' in def ? def.default : (type === 'integer' ? 0 : '');
    const phpDefault = type === 'integer'
        ? String(raw)
        : `'${String(raw).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    return { name, type, nodeType: type === 'integer' ? 'integer' : 'scalar', phpDefault, hasDefault: true };
});

// Extra keys unique to each section definition (name, description).
function extraKeys(defName) {
    const def = schema.$defs[defName];
    if (!def?.properties) return [];
    return Object.keys(def.properties).map(name => ({ name }));
}

// Build section descriptors; each section carries its own keys list so the
// template can decide whether to emit ->defaultValue() per section.
const sections = [
    {
        name: 'settings',
        addDefaultsIfNotSet: true,
        useAttributeAsKey: false,
        extraKeys: [],
        keys: configKeys.map(k => ({ ...k, showDefault: true })),
    },
    {
        name: 'environments',
        addDefaultsIfNotSet: false,
        useAttributeAsKey: true,
        extraKeys: extraKeys('environment'),
        keys: configKeys.map(k => ({ ...k, showDefault: false })),
    },
    {
        name: 'profiles',
        addDefaultsIfNotSet: false,
        useAttributeAsKey: true,
        extraKeys: extraKeys('profile'),
        keys: configKeys.map(k => ({ ...k, showDefault: false })),
    },
    {
        name: 'devices',
        addDefaultsIfNotSet: false,
        useAttributeAsKey: true,
        extraKeys: extraKeys('device'),
        keys: configKeys.map(k => ({ ...k, showDefault: false })),
    },
];

const templateSrc = readFileSync(resolve(root, 'tooling/templates/InVRTConfiguration.tpl.php'), 'utf8');
const template = Handlebars.compile(templateSrc, { noEscape: true });
const output = template({ configKeys, sections });

const outPath = resolve(root, 'src/Input/InvrtConfiguration.php');
writeFileSync(outPath, output);
console.log(`Generated ${outPath}`);
