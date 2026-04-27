import { existsSync } from "node:fs";
import { loadReport } from "@/lib/loadReport";
import type { ReportData } from "@/lib/types";

/**
 * Resolve & cache the report. Inputs can be overridden via environment
 * variables so the Playwright reporter can point Astro at an arbitrary
 * report directory outside the package itself.
 */
let cached: ReportData | undefined;

export function getReport(): ReportData {
  if (cached) return cached;
  const planPath = process.env.INVRT_DIRECTORY + "./plan.yaml";
  const resultsPath = process.env.INVRT_DIRECTORY + "./report.json";
  cached = loadReport({ planPath, resultsPath });
  return cached;
}
