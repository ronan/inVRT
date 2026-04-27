/**
 * Domain types for the InVRT report.
 */

export type TestStatus = "untested" | "approved" | "changed" | "failed" ;

export interface Project {
  url: string;
  id: string;
  title: string;
  checked_at?: string;
}

export interface Environment {
  url: string;
}

export interface Device {
  viewport: { width: number; height: number };
}

export interface PageRecord {
  /** Unique test id from plan.yaml */
  id: string;
  /** URL path of the page, e.g. "/", "/products/pants/" */
  path: string;
  /** Human-friendly title */
  title: string;
  /** Profiles tested for this page */
  profiles: string[];
  /** Final status derived from results.json */
  status: TestStatus;
  /** ISO timestamp the page was last checked (or undefined if untested) */
  checkedAt?: string;
  /** Path (relative to the served report root) of the reference screenshot */
  referenceScreenshot?: string;
  /** Path of the actual/test screenshot */
  testScreenshot?: string;
  /** Path of the diff screenshot, if any */
  diffScreenshot?: string;
  /** Duration in ms */
  durationMs?: number;
  /** Percentage of pixels changed for visual mismatches */
  changePercent?: number;
  /** Error message if failed */
  errorMessage?: string;
}

export interface ReportStatusCounts {
  untested: number;
  approved: number;
  changed: number;
  failed: number;
}

export interface ReportStats {
  totalPages: number;
  testedPages: number;
  durationMs?: number;
  runStartedAt?: string;
  checkedAt?: string;
  outcome?: string;
  counts: ReportStatusCounts;
}

export interface Plan {
  project: Project;
  environments: Record<string, Environment>;
  profiles: Record<string, Record<string, unknown>>;
  devices: Record<string, Device>;
  exclude?: string[];
  pages: Record<string, unknown>;
}

export interface PlaywrightAttachment {
  name: string;
  contentType: string;
  path?: string;
  body?: string;
}

export interface PlaywrightTestResult {
  status: string;
  duration: number;
  errors: Array<{ message?: string; stack?: string }>;
  startTime: string;
  attachments: PlaywrightAttachment[];
}

export interface PlaywrightTest {
  expectedStatus: string;
  status: string;
  results: PlaywrightTestResult[];
}

export interface PlaywrightSpec {
  title: string;
  ok: boolean;
  tags: string[];
  tests: PlaywrightTest[];
  id: string;
  file?: string;
}

export interface PlaywrightSuite {
  title: string;
  file?: string;
  specs?: PlaywrightSpec[];
  suites?: PlaywrightSuite[];
}

export interface PlaywrightReport {
  config?: { version?: string };
  suites: PlaywrightSuite[];
  errors: unknown[];
  stats: {
    startTime: string;
    duration: number;
    expected: number;
    skipped: number;
    unexpected: number;
    flaky: number;
    outcome?: string;
  };
}

export interface ReportData {
  project: Project;
  environments: Record<string, Environment>;
  profiles: string[];
  devices: Record<string, Device>;
  pages: PageRecord[];
  /** Reference screenshot for the home page, if available */
  homeReference?: string;
  generatedAt: string;
  stats: ReportStats;
}
