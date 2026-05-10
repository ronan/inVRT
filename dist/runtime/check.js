import http from 'node:http';
import https from 'node:https';
import yaml from 'js-yaml';
import log from './logger.js';
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
            headers: { 'User-Agent': process.env.INVRT_USER_AGENT || 'InVRT/1.0' },
            rejectUnauthorized: false,
        };
        const req = lib.request(options, (res) => {
            const { statusCode, headers } = res;
            if (follow && statusCode !== undefined && statusCode >= 300 && statusCode < 400 && headers.location) {
                if (redirectCount >= 10) {
                    reject(new Error(`Too many redirects from ${url}`));
                    return;
                }
                redirectCount += 1;
                res.resume();
                const next = headers.location.startsWith('http')
                    ? headers.location
                    : new URL(headers.location, currentUrl).href;
                doRequest(next);
                return;
            }
            let body = '';
            res.on('data', (chunk) => {
                body += chunk;
            });
            res.on('end', () => {
                resolve({ statusCode, finalUrl: currentUrl, body, redirectCount });
            });
        });
        req.on('error', reject);
        req.setTimeout(15000, () => {
            req.destroy(new Error(`Request timed out: ${currentUrl}`));
        });
        req.end();
    };
    doRequest(url);
});
const decodeHtmlEntities = (title) => title
    .replace(/<[^>]+>/gu, '')
    .replace(/&amp;/gu, '&')
    .replace(/&lt;/gu, '<')
    .replace(/&gt;/gu, '>')
    .replace(/&#(\d+);/gu, (_, value) => String.fromCodePoint(Number(value)));
const run = async () => {
    const { INVRT_URL } = process.env;
    if (!INVRT_URL) {
        log.error('INVRT_URL must be set');
        process.exit(1);
    }
    log.info(`🔍 Checking site at ${INVRT_URL}`);
    let result;
    try {
        result = await request(INVRT_URL, { follow: true });
    }
    catch (error) {
        log.error(`Failed to connect to ${INVRT_URL}: ${error.message}`);
        process.exit(1);
        return;
    }
    const { finalUrl, body } = result;
    const titleMatch = body.match(/<title[^>]*>([\s\S]*?)<\/title>/iu);
    const title = titleMatch ? decodeHtmlEntities(titleMatch[1]).trim() : '';
    const isHttps = finalUrl.startsWith('https://');
    process.stdout.write(yaml.dump({
        url: finalUrl.replace(/\/$/u, ''),
        title,
        checked_at: new Date().toISOString(),
    }, { lineWidth: -1 }));
    log.info(`✓ Site check complete. Title: "${title}". HTTPS: ${isHttps ? 'yes' : 'no'}.`);
};
run().catch((error) => {
    log.error(error.message || String(error));
    process.exit(1);
});
