#!/usr/bin/env node

/**
 * Validates docs/config.schema.yaml is a valid JSON Schema and that
 * docs/config.example.yaml passes validation against it.
 */

import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import yaml from 'js-yaml';
import Ajv from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '..');

let exitCode = 0;

function pass(msg) { console.log(`  ✅ ${msg}`); }
function fail(msg) { console.error(`  ❌ ${msg}`); exitCode = 1; }

function loadYaml(relPath) {
    const abs = resolve(root, relPath);
    try {
        return yaml.load(readFileSync(abs, 'utf8'));
    } catch (e) {
        fail(`Failed to parse ${relPath}: ${e.message}`);
        process.exit(1);
    }
}

const ajv = new Ajv({ strict: true, allErrors: true });
addFormats(ajv);

console.log('\nValidating docs/config.schema.yaml\n');

const schema = loadYaml('docs/config.schema.yaml');

let validate;
try {
    validate = ajv.compile(schema);
    pass('Schema is a valid JSON Schema (draft 2020-12)');
} catch (e) {
    fail(`Schema is invalid: ${e.message}`);
    process.exit(1);
}

console.log('\nValidating docs/config.example.yaml against schema\n');

const config = loadYaml('docs/config.example.yaml');
const valid = validate(config);

if (valid) {
    pass('config.example.yaml is valid');
} else {
    fail('config.example.yaml failed validation:');
    for (const err of validate.errors) {
        console.error(`       ${err.instancePath || '(root)'} ${err.message}`);
    }
}

console.log();
process.exit(exitCode);
