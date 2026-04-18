#!/usr/bin/env node

/**
 * Generates app.js from docs/planning/spec/Application.yaml
 * using the Handlebars template at tooling/templates/app.tpl.js.
 */



import { readFileSync, writeFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import yaml from 'js-yaml';
import Handlebars from 'handlebars';


const __dirname = dirname(fileURLToPath(import.meta.url));
const root = resolve(__dirname, '../..');
const templatePath = resolve(root, 'tooling/templates/app.tpl.js');
const outPath = resolve(root, 'scratch/app.js');

const schema = yaml.load(readFileSync(resolve(root, 'docs/spec/Application.yaml'), 'utf8'));

Handlebars.registerHelper('json', function (context) {
    return JSON.stringify(context, null, 2);
});
Handlebars.registerHelper('matches', function (a, b) {
    return a === b;
});

const templateSrc = readFileSync(templatePath, 'utf8');
const template = Handlebars.compile(templateSrc);
writeFileSync(outPath, template({ app: schema }));
console.log(`Generated ${outPath}`);

