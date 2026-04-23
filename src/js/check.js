const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');
const yaml = require('js-yaml');
const log = require('./logger');

const {
  INVRT_URL,
  INVRT_CHECK_FILE,
  INVRT_USER_AGENT,
} = process.env;

/** Make an HTTP/HTTPS request and return a promise of { statusCode, finalUrl, body, redirectCount }. */
const request = (url, { follow = true, headOnly = false } = {}) => new Promise((resolve, reject) => {
  let redirectCount = 0;

  const doRequest = (currentUrl) => {
    const parsed = new URL(currentUrl);
    const lib = parsed.protocol === 'https:' ? https : http;
    const options = {
      hostname: parsed.hostname,
      port: parsed.port || (parsed.protocol === 'https:' ? 443 : 80),
      path: parsed.pathname + parsed.search,
      method: headOnly ? 'HEAD' : 'GET',
      headers: { 'User-Agent': INVRT_USER_AGENT || 'InVRT/1.0' },
      rejectUnauthorized: false,
    };

    const req = lib.request(options, (res) => {
      const { statusCode, headers } = res;

      if (follow && statusCode >= 300 && statusCode < 400 && headers.location) {
        if (redirectCount >= 10) {
          return reject(new Error(`Too many redirects from ${url}`));
        }
        redirectCount++;
        res.resume();
        const next = headers.location.startsWith('http')
          ? headers.location
          : new URL(headers.location, currentUrl).href;
        return doRequest(next);
      }

      let body = '';
      res.on('data', (chunk) => { body += chunk; });
      res.on('end', () => resolve({ statusCode, finalUrl: currentUrl, body, redirectCount }));
    });

    req.on('error', reject);
    req.setTimeout(15000, () => { req.destroy(new Error(`Request timed out: ${currentUrl}`)); });
    req.end();
  };

  doRequest(url);
});

const run = async () => {
  if (!INVRT_URL) {
    log.error('INVRT_URL must be set');
    process.exit(1);
  }
  if (!INVRT_CHECK_FILE) {
    log.error('INVRT_CHECK_FILE must be set');
    process.exit(1);
  }

  log.info(`🔍 Checking site at ${INVRT_URL}`);

  let initialStatusCode = 0;
  try {
    const head = await request(INVRT_URL, { follow: false, headOnly: true });
    initialStatusCode = head.statusCode;
  } catch {
    // Non-fatal — will be caught in main request below if site is truly unreachable.
  }

  let result;
  try {
    result = await request(INVRT_URL, { follow: true });
  } catch (err) {
    log.error(`Failed to connect to ${INVRT_URL}: ${err.message}`);
    process.exit(1);
  }

  const { finalUrl, body, redirectCount } = result;

  const redirectedFrom = redirectCount > 0 && initialStatusCode === 301
    ? INVRT_URL.replace(/\/$/, '')
    : null;

  const titleMatch = body.match(/<title[^>]*>([\s\S]*?)<\/title>/i);
  const title = titleMatch
    ? titleMatch[1].replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n))).trim()
    : '';

  const isHttps = finalUrl.startsWith('https://');

  const data = {
    url: finalUrl.replace(/\/$/, ''),
    title,
    https: isHttps,
    ...(redirectedFrom ? { redirected_from: redirectedFrom } : {}),
    checked_at: new Date().toISOString(),
  };

  const dir = path.dirname(INVRT_CHECK_FILE);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

  fs.writeFileSync(INVRT_CHECK_FILE, yaml.dump(data, { lineWidth: -1 }));

  log.info(`✓ Site check complete. Title: "${title}". HTTPS: ${isHttps ? 'yes' : 'no'}. Written to ${INVRT_CHECK_FILE}`);
};

run();
