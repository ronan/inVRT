#!/usr/bin/env node

/**
 * Generates src/Input/InvrtConfiguration.php from docs/config.schema.yaml
 * using the Handlebars template at src/Input/InVRTConfiguration.tpl.php.
 */



import $RefParser from "@apidevtools/json-schema-ref-parser";
import { readFileSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import yaml from 'js-yaml';
import Handlebars from 'handlebars';


const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '../..');
const templatePath = resolve(root, 'src/Input/INCON.hbl.php');
const outPath = resolve(root, 'src/Input/INCON.php');

const schema = yaml.load(readFileSync(resolve(root, 'docs/config.schema.yaml'), 'utf8'));

try {
    await $RefParser.dereference(schema);
    // note - by default, schema is modified in place, 

} catch (err) {
    console.error(err);
}


Handlebars.registerHelper('json', function (context) {
    return JSON.stringify(context, null, 2);
});
Handlebars.registerHelper('matches', function (a, b) {
    return a === b;
});

const templateSrc = readFileSync(templatePath, 'utf8');
const template = Handlebars.compile(templateSrc);
writeFileSync(outPath, template({ schema: schema }));
console.log(`Generated ${outPath}`);

