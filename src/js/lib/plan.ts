import yaml from 'js-yaml';

import { extractPagesFromPlan, type ExtractedPage } from './plan-pages.js';

type PlanFile = {
  project?: Record<string, unknown>;
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const loadPlan = (content: string): PlanFile => {
  if (content.trim() === '') {
    return {};
  }

  const parsed = yaml.load(content);
  return isRecord(parsed) ? parsed as PlanFile : {};
};

export const pages = (content: string): ExtractedPage[] => extractPagesFromPlan(content);

export const project = (content: string): Record<string, unknown> | undefined => loadPlan(content).project;
