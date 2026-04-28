import { existsSync } from "node:fs";
import { join } from "node:path";
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
  const dir = process.env.INVRT_DIRECTORY ?? "";
  const planPath = join(dir, "plan.yaml");
  const resultsPath = join(dir, "report.json");
  cached = loadReport({ planPath, resultsPath });
  return cached;
}
