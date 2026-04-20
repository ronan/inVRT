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

const schema = yaml.load(readFileSync(resolve(root, 'docs/spec/Application.yaml'), 'utf8'));

Handlebars.registerHelper('json', function (context) {
  return JSON.stringify(context, null, 2);
});
Handlebars.registerHelper('matches', function (a, b) {
  return a === b;
});
Handlebars.registerHelper('snake_case', function (text, spaces) {
  return text.replace(/\s+|-/g, '_').toLowerCase();
});


function buildTemplate(schema, templatePath, outPath) {
  let templateSrc = readFileSync(templatePath, 'utf8');
  templateSrc = templateSrc.replace(/^\s*#(.*)$/gm, '$1');
  const template = Handlebars.compile(templateSrc);
  writeFileSync(outPath, template({ app: schema }));
  console.log(`Generated ${outPath}`);
}
buildTemplate(schema, resolve(root, 'tooling/templates/app.tpl.js'), resolve(root, 'scratch/app.js'));
buildTemplate(schema, resolve(root, 'tooling/templates/ConfigSchema.tpl.php'), resolve(root, 'src/core/ConfigSchema.php'));
