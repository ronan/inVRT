import { existsSync, readFileSync, readdirSync } from "node:fs";
import { basename, dirname, extname, relative, resolve, sep } from "node:path";
import yaml from "js-yaml";
import type {
  PageRecord,
  Plan,
  PlaywrightReport,
  PlaywrightSpec,
  PlaywrightSuite,
  ReportData,
  ReportStatusCounts,
  TestStatus,
} from "./types";

/**
 * Walk the recursive `pages` tree from plan.yaml and emit a flat
 * list of PageRecord stubs. Path is the concatenation of all keys
 * leading to a node that has both `id` and `title`.
 */
function walkPages(
  node: Record<string, unknown>,
  prefix: string,
): Array<Omit<PageRecord, "status">> {
  const out: Array<Omit<PageRecord, "status">> = [];

  // If this node itself looks like a page, record it.
  if (
    typeof (node as { id?: unknown }).id === "string" &&
    typeof (node as { title?: unknown }).title === "string"
  ) {
    const path = prefix === "" ? "/" : prefix;
    out.push({
      id: (node as { id: string }).id,
      title: (node as { title: string }).title,
      path,
      profiles: Array.isArray((node as { profiles?: unknown }).profiles)
        ? ((node as { profiles: unknown[] }).profiles as string[])
        : [],
    });
  }

  // Recurse into "/..." children.
  for (const [key, value] of Object.entries(node)) {
    
    if (
      !key.startsWith("/") && 
      !key.startsWith(".") && 
      !key.startsWith("?") && 
      !key.startsWith("#")
    ) continue;
    

    if (value && typeof value === "object") {
      // Combine: prefix + key. Special case: child key "/", "" or "." means index of parent.
      let childPrefix: string;
      if (key === "/" || key === '' || key === ".") {
        childPrefix = prefix === "" ? "" : prefix; // index page of parent (path is parent path)
        // For "/" under a parent, the meaningful path *is* the parent prefix
        // but we still want to add a trailing slash to disambiguate the index.
        childPrefix = prefix + "/";
      } else {
        childPrefix = prefix + key;
      }
      out.push(...walkPages(value as Record<string, unknown>, childPrefix));
    }
  }

  return out;
}

function normalizePath(value: string): string {
  return value.split(sep).join("/");
}

function stripAnsi(value: string | undefined): string | undefined {
  return value?.replace(/\u001B\[[0-9;]*m/g, "");
}

function extractChangePercent(value: string | undefined): number | undefined {
  const stripped = stripAnsi(value)?.trim();
  if (!stripped) return undefined;

  const match = /ratio\s+([0-9]*\.?[0-9]+)\s+of all image pixels/i.exec(stripped);
  if (!match) return undefined;

  const parsed = Number(match[1]);
  return Number.isFinite(parsed) ? parsed : undefined;
}

function isSnapshotMismatch(value: string | undefined): boolean {
  const stripped = stripAnsi(value);
  if (!stripped) return false;
  return /toMatchSnapshot/i.test(stripped) || /Snapshot:/i.test(stripped) || /image pixels/i.test(stripped);
}

function formatFailureMessage(value: string | undefined): string | undefined {
  const stripped = stripAnsi(value)?.trim();
  if (!stripped) return undefined;

  const lines = stripped.split("\n");
  const cleaned: string[] = [];

  for (const line of lines) {
    if (/^\s+at\s+.+/.test(line)) break;
    cleaned.push(line);
  }

  return cleaned.join("\n").trim() || stripped;
}

function listRelativeFiles(rootDir: string, childDir: string): string[] {
  const baseDir = resolve(rootDir, childDir);
  if (!existsSync(baseDir)) return [];

  const out: string[] = [];
  const walk = (currentDir: string) => {
    for (const entry of readdirSync(currentDir, { withFileTypes: true })) {
      const fullPath = resolve(currentDir, entry.name);
      if (entry.isDirectory()) {
        walk(fullPath);
        continue;
      }
      out.push(normalizePath(relative(rootDir, fullPath)));
    }
  };

  walk(baseDir);
  return out;
}

interface AssetIndex {
  approved: Map<string, string>;
  actual: Map<string, string>;
  diff: Map<string, string>;
  expected: Map<string, string>;
  results: Map<string, string>;
}

function buildAssetIndex(reportRoot: string): AssetIndex {
  const index: AssetIndex = {
    approved: new Map(),
    actual: new Map(),
    diff: new Map(),
    expected: new Map(),
    results: new Map(),
  };

  for (const relativePath of listRelativeFiles(reportRoot, "approved")) {
    const fileName = basename(relativePath, extname(relativePath));
    if (fileName) {
      index.approved.set(fileName, relativePath);
    }
  }

  for (const relativePath of listRelativeFiles(reportRoot, "results")) {
    const fileName = basename(relativePath);
    const exact = /^([a-z0-9]+)\.png$/i.exec(fileName);
    const actual = /^([a-z0-9]+)-actual\.png$/i.exec(fileName);
    const expected = /^([a-z0-9]+)-expected\.png$/i.exec(fileName);
    const diff = /^([a-z0-9]+)-diff\.png$/i.exec(fileName);

    if (exact) {
      index.results.set(exact[1], relativePath);
    } else if (actual) {
      index.actual.set(actual[1], relativePath);
    } else if (expected) {
      index.expected.set(expected[1], relativePath);
    } else if (diff) {
      index.diff.set(diff[1], relativePath);
    }
  }

  return index;
}

function toReportRelativePath(reportRoot: string, filePath: string | undefined): string | undefined {
  if (!filePath) return undefined;

  const resolved = resolve(filePath);
  const reportRootResolved = resolve(reportRoot);
  const reportRootPrefix = `${reportRootResolved}${sep}`;
  if (resolved === reportRootResolved || resolved.startsWith(reportRootPrefix)) {
    return normalizePath(relative(reportRootResolved, resolved));
  }

  const match = /(?:^|[\\/])(approved|results)[\\/](.+)$/.exec(filePath);
  if (match) {
    return `${match[1]}/${match[2].replace(/\\/g, "/")}`;
  }

  return undefined;
}

/**
 * Recursively collect all specs from a Playwright report.
 */
function collectSpecs(suite: PlaywrightSuite): PlaywrightSpec[] {
  const specs: PlaywrightSpec[] = [];
  if (suite.specs) specs.push(...suite.specs);
  if (suite.suites) {
    for (const child of suite.suites) specs.push(...collectSpecs(child));
  }
  return specs;
}

/**
 * Build a map of test-id -> spec from a Playwright report.
 * Specs are tagged with `id-<id>` in plan-driven test files.
 */
function indexSpecsById(report: PlaywrightReport): Map<string, PlaywrightSpec> {
  const all = report.suites.flatMap(collectSpecs);
  const map = new Map<string, PlaywrightSpec>();
  for (const spec of all) {
    for (const tag of spec.tags ?? []) {
      const m = /^id-(.+)$/.exec(tag);
      if (m) map.set(m[1], spec);
    }
  }
  return map;
}

function specToPage(
  spec: PlaywrightSpec | undefined,
  pageId: string,
  reportRoot: string,
  assets: AssetIndex,
): {
  status: TestStatus;
  checkedAt?: string;
  durationMs?: number;
  changePercent?: number;
  errorMessage?: string;
  reference?: string;
  test?: string;
  diff?: string;
} {
  if (!spec) return { status: "untested" };

  const test = spec.tests[0];
  const result = test?.results[test.results.length - 1];
  if (!result) return { status: "untested" };
  const attachments = result.attachments ?? [];
  const findAttachment = (name: string) =>
    attachments.find((attachment) => attachment.name === name)?.path ??
    attachments.find((attachment) => attachment.name.toLowerCase().includes(name))?.path;
  const fallbackScreenshot = toReportRelativePath(reportRoot, findAttachment("screenshot"));
  const rawErrorMessage = result.errors?.[0]?.message;
  const changePercent = extractChangePercent(rawErrorMessage);
  const snapshotMismatch = isSnapshotMismatch(rawErrorMessage);
  const reference =
    toReportRelativePath(reportRoot, findAttachment("expected")) ??
    toReportRelativePath(reportRoot, findAttachment("reference")) ??
    assets.approved.get(pageId) ??
    assets.expected.get(pageId);
  const actual =
    toReportRelativePath(reportRoot, findAttachment("actual")) ??
    assets.actual.get(pageId) ??
    assets.results.get(pageId) ??
    fallbackScreenshot;
  const diff =
    toReportRelativePath(reportRoot, findAttachment("diff")) ??
    assets.diff.get(pageId);

  let status: TestStatus = "untested";
  if (spec.ok && result.status === "passed") {
    status = "approved";
  } else if (!spec.ok && (diff || (reference && actual))) {
    status = "changed";
  } else if (!spec.ok) {
    status = "failed";
  }

  return {
    status,
    checkedAt: result.startTime,
    durationMs: result.duration,
    changePercent: status === "changed" && snapshotMismatch ? changePercent : undefined,
    errorMessage: status === "failed" ? formatFailureMessage(rawErrorMessage) : undefined,
    reference,
    test: actual,
    diff,
  };
}

export interface LoadOptions {
  planPath: string;
  resultsPath: string;
}

export function loadReport(opts: LoadOptions): ReportData {
  const plan = yaml.load(readFileSync(resolve(opts.planPath), "utf8")) as Plan;
  const report = JSON.parse(readFileSync(resolve(opts.resultsPath), "utf8")) as PlaywrightReport;
console.log("Plan and report loaded. Processing data...");
  const reportRoot = dirname(resolve(opts.resultsPath));
  const assets = buildAssetIndex(reportRoot);
  const flat = walkPages(plan.pages as Record<string, unknown>, "");
  const specsById = indexSpecsById(report);
  const pages: PageRecord[] = flat.map((p) => {
    const profiles = p.profiles.length > 0 ? p.profiles : ["anonymous"];
    const s = specToPage(specsById.get(p.id), p.id, reportRoot, assets);


    return {
      ...p,
      profiles,
      status: s.status,
      checkedAt: s.checkedAt,
      durationMs: s.durationMs,
      changePercent: s.changePercent,
      errorMessage: s.errorMessage,
      referenceScreenshot: s.reference,
      testScreenshot: s.test,
      diffScreenshot: s.diff,
    };

  });

  // Deduplicate by id (keys can collide e.g. zzcdsbvfzj appears twice in plan)
  // Keep first occurrence but merge profiles.
  const seen = new Map<string, PageRecord>();
  for (const p of pages) {
    const prev = seen.get(p.id);
    if (prev) {
      prev.profiles = Array.from(new Set([...prev.profiles, ...p.profiles]));
    } else {
      seen.set(p.id, p);
    }
  }

  const dedupedPages = Array.from(seen.values());
  const home = dedupedPages.find((page) => page.path === "/");
  const counts = dedupedPages.reduce<ReportStatusCounts>(
    (acc, page) => {
      acc[page.status] += 1;
      return acc;
    },
    { untested: 0, approved: 0, changed: 0, failed: 0 },
  );

  return {
    project: plan.project,
    environments: plan.environments ?? {},
    profiles: Object.keys(plan.profiles ?? {}),
    devices: plan.devices ?? {},
    pages: dedupedPages,
    homeReference: home?.referenceScreenshot,
    generatedAt: new Date().toISOString(),
    stats: {
      totalPages: dedupedPages.length,
      testedPages: dedupedPages.length - counts.untested,
      durationMs: report.stats?.duration,
      runStartedAt: report.stats?.startTime,
      checkedAt: plan.project.checked_at ?? report.stats?.startTime,
      outcome: report.stats?.outcome,
      counts,
    },
  };
}
