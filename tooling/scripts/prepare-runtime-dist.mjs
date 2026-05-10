#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const dir = path.join(root, 'dist', 'runtime');

fs.mkdirSync(dir, { recursive: true });
fs.writeFileSync(
  path.join(dir, 'package.json'),
  `${JSON.stringify({ type: 'module' }, null, 2)}\n`,
);
fs.copyFileSync(
  path.join(root, 'src', 'js', 'report.sqrl'),
  path.join(dir, 'report.sqrl'),
);
