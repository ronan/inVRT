import type { PageRecord, TestStatus } from "./types";

export const STATUS_LABELS: Record<TestStatus, string> = {
  changed: "Changed",
  approved: "Approved",
  failed: "Failed",
  untested: "Untested",
};

export const STATUS_VARIANTS: Record<TestStatus, string> = {
  untested: "outline",
  approved: "success",
  changed: "warning",
  failed: "error",
};



export function statusFiltersAll(pages: PageRecord[]): { 
    value: TestStatus; 
    label: string; 
    variant: string;
    count: number;
  }[] {
  const statusEntries = Object.entries(STATUS_LABELS) as Array<[keyof typeof STATUS_LABELS, string]>;
  return Array.from(statusEntries).map(([status, label]) => ({
    value: status,
    label: STATUS_LABELS[status],
    variant: STATUS_VARIANTS[status],
    count: pages.filter((page) => page.status === status).length,
  }));
}

export function statusLabel(page: PageRecord): string {
  const { status, changePercent } = page;
  if (changePercent) {
    return formatChangePercent(changePercent);
  }
  return STATUS_LABELS[status];
}

export function statusVariant(page: PageRecord) {
  const { status } = page;
  return STATUS_VARIANTS[status];
}

/** Read the package version of the parent invrt project. Falls back to "0.0.0". */
export function readVersion(): string {
  return process.env.INVRT_VERSION ?? "0.0.0";
}

function formatChangePercent(changePercent: number): string {
  if (changePercent === undefined) return "";
  const rounded =
    changePercent >= 10 ? changePercent.toFixed(0) :
    changePercent >= 1 ? changePercent.toFixed(1) :
    changePercent.toFixed(2);
  return `${rounded.replace(/\.0+$/, "").replace(/(\.\d*[1-9])0+$/, "$1")}% changed`;
}