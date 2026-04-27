/**
 * InVRT Astro Reporter
 * --------------------
 *
 * A Playwright custom reporter that serializes test results into
 * Playwright's JSON report shape, then invokes the Astro report package
 * to produce a single static `index.html` in the target report directory.
 *
 * Usage in playwright.config.ts:
 *
 *   reporter: [
 *     ['./reporters/invrt-astro-reporter.ts', {
 *       inputDir: './.invrt',
 *       astroProject: './invrt-reporter',
 *     }],
 *   ],
 */

import { execFileSync } from "node:child_process";
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import type {
  FullConfig,
  FullResult,
  Reporter,
  Suite,
  TestCase,
  TestResult,
} from "@playwright/test/reporter";

interface InvrtReporterOptions {
  /** Root directory containing plan.yaml, report.json, approved/, and results/. */
  inputDir?: string;
  /** Path to the plan.yaml. Defaults to "<inputDir>/plan.yaml". */
  plan?: string;
  /** Path to the JSON report file. Defaults to "<inputDir>/report.json". */
  results?: string;
  /** Path to the Astro project that builds the report. Default: this folder's invrt-reporter. */
  astroProject?: string;
}

interface SerializedAttachment {
  name: string;
  contentType: string;
  path?: string;
}

interface SerializedTestResult {
  status: string;
  duration: number;
  errors: Array<{ message?: string }>;
  startTime: string;
  attachments: SerializedAttachment[];
}

interface SerializedSpec {
  title: string;
  ok: boolean;
  tags: string[];
  tests: Array<{
    expectedStatus: string;
    status: string;
    results: SerializedTestResult[];
  }>;
  id: string;
  file?: string;
}

class InvrtAstroReporter implements Reporter {
  private options: {
    inputDir: string;
    plan: string;
    results: string;
    astroProject: string;
  };
  private specs = new Map<TestCase, SerializedSpec>();
  private startTime = new Date();

  constructor(options: InvrtReporterOptions = {}) {
    const inputDir = options.inputDir ?? "./invrt";
    this.options = {
      inputDir,
      plan: options.plan ?? join(inputDir, "plan.yaml"),
      results: options.results ?? join(inputDir, "report.json"),
      astroProject: options.astroProject ?? join(__dirname, "..", "invrt-reporter"),
    };
  }

  onBegin(_config: FullConfig, _suite: Suite): void {
    this.startTime = new Date();
  }

  onTestEnd(test: TestCase, result: TestResult): void {
    const tags = (test.tags ?? []) as string[];
    const id = test.id;
    const existing = this.specs.get(test);
    const serializedResult: SerializedTestResult = {
      status: result.status,
      duration: result.duration,
      errors: (result.errors ?? []).map((e) => ({ message: e.message })),
      startTime: result.startTime instanceof Date ? result.startTime.toISOString() : String(result.startTime),
      attachments: (result.attachments ?? []).map((a) => ({
        name: a.name,
        contentType: a.contentType,
        path: a.path,
      })),
    };

    if (existing) {
      existing.tests[0].results.push(serializedResult);
      existing.ok = existing.ok && result.status === test.expectedStatus;
    } else {
      this.specs.set(test, {
        title: test.title,
        ok: result.status === test.expectedStatus,
        tags,
        tests: [
          {
            expectedStatus: test.expectedStatus,
            status: result.status === test.expectedStatus ? "expected" : "unexpected",
            results: [serializedResult],
          },
        ],
        id,
        file: test.location?.file,
      });
    }
  }

  async onEnd(result: FullResult): Promise<void> {
    const astroProject = resolve(this.options.astroProject);
    const inputDir = resolve(this.options.inputDir);
    const planPath = resolve(this.options.plan);
    const resultsPath = resolve(this.options.results);

    mkdirSync(dirname(resultsPath), { recursive: true });
    const specs = Array.from(this.specs.values());

    const report = {
      config: { version: "1.0.0" },
      suites: [
        {
          title: "invrt",
          file: "invrt",
          specs,
        },
      ],
      errors: [],
      stats: {
        startTime: this.startTime.toISOString(),
        duration: Date.now() - this.startTime.getTime(),
        expected: specs.filter((s) => s.ok).length,
        skipped: 0,
        unexpected: specs.filter((s) => !s.ok).length,
        flaky: 0,
        outcome: result.status,
      },
    };

    writeFileSync(resultsPath, JSON.stringify(report, null, 2), "utf8");

    let version = "0.0.0";
    const env = {
      ...process.env,
      INVRT_PLAN: planPath,
      INVRT_RESULTS: resultsPath,
      INVRT_VERSION: version,
    };

    try {
      execFileSync("npx", ["astro", "build", "--outDir", inputDir], {
        cwd: astroProject,
        stdio: "inherit",
        env,
      });
      // eslint-disable-next-line no-console
      console.log(`\n[invrt] Report written to ${inputDir}`);
    } catch (err) {
      // eslint-disable-next-line no-console
      console.error("[invrt] Failed to build report:", err);
    }
  }
}

export default InvrtAstroReporter;
