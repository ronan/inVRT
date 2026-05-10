import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Sqrl from 'squirrelly';

import log from './logger.js';
import * as plan from './lib/plan.js';

type JsonValue = Record<string, unknown>;
type ReportSpec = {
  tags?: string[];
  tests?: Array<{ results?: Array<{ attachments?: unknown; status?: string }> }>;
};

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const readJson = (file: string, fallback: JsonValue): JsonValue => {
  if (!fs.existsSync(file)) {
    return fallback;
  }

  return JSON.parse(fs.readFileSync(file, 'utf8')) as JsonValue;
};

const normalizeStats = (report: JsonValue, generatedAt: string): Record<string, unknown> => {
  const stats = typeof report.stats === 'object' && report.stats !== null ? report.stats as Record<string, unknown> : {};
  return {
    ...stats,
    startTime: typeof stats.startTime === 'string' && stats.startTime !== '' ? stats.startTime : generatedAt,
    duration: Number.isFinite(stats.duration) ? stats.duration : 0,
  };
};

const collectSpecs = (report: JsonValue): ReportSpec[] =>
  Array.isArray(report.suites)
    ? report.suites.flatMap((suite) => Array.isArray((suite as JsonValue).specs) ? (suite as JsonValue).specs as ReportSpec[] : [])
    : [];

const buildFiles = (spec: ReportSpec, root: string): Record<string, { name: string; path: string }> => {
  const result = spec.tests?.[0]?.results?.[0] ?? {};
  const attachments = Array.isArray(result.attachments) ? result.attachments as Array<Record<string, unknown>> : [];
  const files: Record<string, { name: string; path: string }> = {};

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

const renderFallbackReport = (projectData: Record<string, unknown>, generatedAt: string): string => `<!DOCTYPE html>
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

const run = async (): Promise<void> => {
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
  const root = typeof report.config === 'object' && report.config !== null && typeof (report.config as JsonValue).rootDir === 'string' && (report.config as JsonValue).rootDir !== ''
    ? `${String((report.config as JsonValue).rootDir)}/`
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
    (page as Record<string, unknown>).files = {};

    if (!spec) {
      (page as Record<string, unknown>).status = 'untested';
      data.counts.untested += 1;
      continue;
    }

    const result = spec.tests?.[0]?.results?.[0] ?? {};
    const status = result.status ?? 'untested';

    (page as Record<string, unknown>).spec = spec;
    (page as Record<string, unknown>).test = spec.tests?.[0];
    (page as Record<string, unknown>).results = result;
    (page as Record<string, unknown>).status = status;

    if (status in data.counts) {
      data.counts[status as keyof typeof data.counts] += 1;
    }

    const files = buildFiles(spec, root);
    files.reference = files.expected ?? { name: 'reference', path: `approved/${page.id}.png` };
    files.test = files.actual ?? files.reference;
    files.thumbnail = files.reference;
    (page as Record<string, unknown>).files = files;
  }

  const content = pages.length > 0
    ? Sqrl.render(template, data)
    : renderFallbackReport(projectData, generatedAt);

  fs.writeFileSync(path.join(INVRT_DIRECTORY, 'report-data.json'), JSON.stringify(data));
  fs.writeFileSync(path.join(INVRT_DIRECTORY, 'index.html'), content);
  log.info(`Wrote report to ${INVRT_DIRECTORY}/index.html`);
};

run().catch((error) => {
  log.error((error as Error).message || String(error));
  process.exit(1);
});
