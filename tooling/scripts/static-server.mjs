#!/usr/bin/env node

import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import process from 'node:process';

const [, , root, portRaw] = process.argv;

if (!root || !portRaw) {
  process.stderr.write('Usage: static-server <root> <port>\n');
  process.exit(1);
}

const port = Number.parseInt(portRaw, 10);
const safeRoot = path.resolve(root);

const contentTypes = new Map([
  ['.css', 'text/css; charset=utf-8'],
  ['.html', 'text/html; charset=utf-8'],
  ['.js', 'text/javascript; charset=utf-8'],
  ['.json', 'application/json; charset=utf-8'],
  ['.png', 'image/png'],
  ['.svg', 'image/svg+xml'],
  ['.txt', 'text/plain; charset=utf-8'],
]);

const server = http.createServer((req, res) => {
  const requestPath = new URL(req.url ?? '/', 'http://127.0.0.1').pathname;
  const normalized = requestPath === '/' ? '/index.html' : requestPath;
  const resolved = path.resolve(safeRoot, `.${normalized}`);

  if (!resolved.startsWith(safeRoot)) {
    res.writeHead(403);
    res.end('Forbidden');
    return;
  }

  if (!fs.existsSync(resolved) || fs.statSync(resolved).isDirectory()) {
    res.writeHead(404);
    res.end('Not found');
    return;
  }

  const type = contentTypes.get(path.extname(resolved)) ?? 'application/octet-stream';
  res.writeHead(200, { 'Content-Type': type });
  fs.createReadStream(resolved).pipe(res);
});

server.listen(port, '127.0.0.1');
