#!/usr/bin/env node

import fs from 'node:fs';
import process from 'node:process';
import yaml from 'js-yaml';

const [, , file, dottedPath] = process.argv;

if (!file || !dottedPath) {
  process.stderr.write('Usage: yaml-get <file> <path>\n');
  process.exit(1);
}

const data = yaml.load(fs.readFileSync(file, 'utf8'));
let value = data;

for (const segment of dottedPath.split('.')) {
  if (value === null || typeof value !== 'object' || !(segment in value)) {
    process.stderr.write(`Missing key: ${dottedPath}\n`);
    process.exit(1);
  }

  value = value[segment];
}

if (typeof value === 'boolean') {
  process.stdout.write(value ? 'true' : 'false');
  process.exit(0);
}

if (Array.isArray(value) || (value !== null && typeof value === 'object')) {
  process.stdout.write(JSON.stringify(value));
  process.exit(0);
}

process.stdout.write(String(value));
