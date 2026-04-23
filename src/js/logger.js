const pino = require('pino');

/**
 * Shared pino logger factory for inVRT Node scripts.
 *
 * Emits NDJSON on stderr at all levels (trace+); the PHP NodeOutputParser
 * reads lines and routes them to the PSR-3 logger with appropriate verbosity.
 * stdout is reserved for data output (pipeline output files).
 *
 * timestamp and base (pid/hostname) are omitted — PHP has no use for them.
 */
module.exports = pino({
  level: 'trace',
  base: null,
  timestamp: false,
}, process.stderr);
