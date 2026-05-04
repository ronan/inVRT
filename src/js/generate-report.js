const crypto = require('crypto');
const fs = require('fs');
const { existsSync, readFileSync, readdirSync } = require("fs");
const sqrl = require('squirrelly');
const { resolve, join } = require('path');

const log = require('./logger');
const { readStdin } = require('./lib/stdio');
const plan = require('./lib/plan');

const run = async () => {
  const {
    INVRT_URL,
    INVRT_DIRECTORY,
    INVRT_PLAN_FILE,
    INVRT_REPORT_DIR,
    INVRT_CAPTURE_DIR,
    INVRT_ID,
  } = process.env;

  const planfile = fs.readFileSync(INVRT_PLAN_FILE);
  const pages = plan.pages(planfile);
  const project = plan.project(planfile);
  const report = JSON.parse(readFileSync(resolve(`${INVRT_DIRECTORY}/report.json`)));
  const template = readFileSync(join(__dirname, 'report.sqrl'), "utf8");

  const tested_at = report.stats.startTime;

  const generated_at = new Date().toISOString();

  const data = {
    generated_at,
    tested_at,
    project,
    pages,
    duration_s: (report.stats.duration / 1000).toFixed(1),
    stats: report.stats,
    counts: {
      untested: 0,
      failed: 0,
      changed: 0,
      passed: 0,
    }
  }
  const root = report.config.rootDir + '/';
  
  pages.forEach((page) => {
    page.files = {};
    report.suites[0].specs.forEach((spec) => {
      if (spec.tags && spec.tags.includes(`id-${page.id}`)){
        page.spec = spec;
        page.test = spec.tests[0];
        page.results = page.test.results[0] ?? {};

        page.status = page.results.status ?? 'untested';
        if (page.status && Object.keys(data.counts).includes(page.status)) {
          data.counts[page.status]++;
        }

        Object.values(spec.tests[0].results[0].attachments).forEach(
          (file) => {
            file.path = file.path.startsWith(root) ? file.path.slice(root.length) : file.path;
            page.files[file.name] = file;
          }
        );
        page.files.reference = {path: `approved/${page.id}.png`};
        page.files.test = page.files.screenshot ?? page.files.reference;
        page.files.thumbnail = page.files.test;
      }
    });
    if (!page.status) {
      page.status = 'untested';
      data.counts.untested++;
    }
  });



  if (pages.length === 0) {
    log.error('No testable page paths found in ${}. Trspec.tests[0].results[0].attachments)y running `invrt crawl`.');
    process.exit(1);
  }

  content = sqrl.render(template, data);
  fs.writeFileSync(`${INVRT_DIRECTORY}/report-data.json`, JSON.stringify(data));
  fs.writeFileSync(`${INVRT_DIRECTORY}/index.html`, content);
  log.info(`Wrote report to ${INVRT_DIRECTORY}/index.html`);
};

run();
