import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Sqrl from 'squirrelly';
import log from './logger.js';
import * as plan from './lib/plan.js';
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const readJson = (file, fallback) => {
    if (!fs.existsSync(file)) {
        return fallback;
    }
    return JSON.parse(fs.readFileSync(file, 'utf8'));
};
const normalizeStats = (report, generatedAt) => {
    const stats = typeof report.stats === 'object' && report.stats !== null ? report.stats : {};
    return {
        ...stats,
        startTime: typeof stats.startTime === 'string' && stats.startTime !== '' ? stats.startTime : generatedAt,
        duration: Number.isFinite(stats.duration) ? stats.duration : 0,
    };
};
const collectSpecs = (report) => Array.isArray(report.suites)
    ? report.suites.flatMap((suite) => Array.isArray(suite.specs) ? suite.specs : [])
    : [];
const buildFiles = (spec, root) => {
    const result = spec.tests?.[0]?.results?.[0] ?? {};
    const attachments = Array.isArray(result.attachments) ? result.attachments : [];
    const files = {};
    for (const file of attachments) {
        const filePath = typeof file.path === 'string' ? file.path : '';
        const normalizedPath = root !== '' && filePath.startsWith(root) ? filePath.slice(root.length) : filePath;
        const name = typeof file.name === 'string'
            ? file.name.replace(/[a-z]+-(.+)\.png$/u, '$1')
            : 'attachment';
        files[name] = { name, path: normalizedPath };
    }
    return files;
};
const renderFallbackReport = (projectData, generatedAt) => `<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>${String(projectData.title || projectData.name || 'InVRT')} - InVRT Report</title>
  </head>
  <body>
    <h1>${String(projectData.title || projectData.name || 'InVRT Report')}</h1>
    <p>No testable pages were available when this report was generated.</p>
    <p>Generated: ${generatedAt}</p>
  </body>
</html>
`;
const run = async () => {
    const { INVRT_DIRECTORY, INVRT_PLAN_FILE, INVRT_VERSION } = process.env;
    if (!INVRT_DIRECTORY) {
        log.error('INVRT_DIRECTORY must be set');
        process.exit(1);
    }
    if (!INVRT_PLAN_FILE) {
        log.error('INVRT_PLAN_FILE must be set');
        process.exit(1);
    }
    const generatedAt = new Date().toISOString();
    const reportPath = path.resolve(INVRT_DIRECTORY, 'report.json');
    const planContent = fs.readFileSync(INVRT_PLAN_FILE, 'utf8');
    const pages = plan.pages(planContent);
    const projectData = plan.project(planContent) ?? {};
    const report = readJson(reportPath, {});
    const stats = normalizeStats(report, generatedAt);
    const template = fs.readFileSync(path.join(__dirname, 'report.sqrl'), 'utf8');
    const specs = collectSpecs(report);
    const root = typeof report.config === 'object' && report.config !== null && typeof report.config.rootDir === 'string' && report.config.rootDir !== ''
        ? `${String(report.config.rootDir)}/`
        : '';
    const data = {
        invrt_version: INVRT_VERSION,
        generated_at: generatedAt,
        tested_at: String(stats.startTime),
        project: projectData,
        pages,
        duration_s: (Number(stats.duration) / 1000).toFixed(1),
        stats,
        counts: {
            untested: 0,
            failed: 0,
            changed: 0,
            passed: 0,
            total: pages.length,
        },
    };
    for (const page of pages) {
        const spec = specs.find((entry) => entry.tags?.includes(`id-${page.id}`));
        page.files = {};
        if (!spec) {
            page.status = 'untested';
            data.counts.untested += 1;
            continue;
        }
        const result = spec.tests?.[0]?.results?.[0] ?? {};
        const status = result.status ?? 'untested';
        page.spec = spec;
        page.test = spec.tests?.[0];
        page.results = result;
        page.status = status;
        if (status in data.counts) {
            data.counts[status] += 1;
        }
        const files = buildFiles(spec, root);
        files.reference = files.expected ?? { name: 'reference', path: `approved/${page.id}.png` };
        files.test = files.actual ?? files.reference;
        files.thumbnail = files.reference;
        page.files = files;
    }
    const content = pages.length > 0
        ? Sqrl.render(template, data)
        : renderFallbackReport(projectData, generatedAt);
    fs.writeFileSync(path.join(INVRT_DIRECTORY, 'report-data.json'), JSON.stringify(data));
    fs.writeFileSync(path.join(INVRT_DIRECTORY, 'index.html'), content);
    log.info(`Wrote report to ${INVRT_DIRECTORY}/index.html`);
};
run().catch((error) => {
    log.error(error.message || String(error));
    process.exit(1);
});
