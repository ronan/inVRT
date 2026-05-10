import yaml from 'js-yaml';

export type HookMap = {
  setup?: string;
  onready?: string;
  teardown?: string;
};

export type ExtractedPage = {
  id: string;
  path: string;
  title: string;
  hooks: HookMap;
};

type PlanNode = Record<string, unknown> | string | null | undefined;

const HOOK_ALIASES: Record<keyof HookMap, string[]> = {
  setup: ['setup', 'before'],
  onready: ['onready', 'ready'],
  teardown: ['teardown', 'after'],
};

const CHILD_KEY_PATTERN = /^$|^\/$|^\//u;

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const isChildKey = (key: string): boolean => CHILD_KEY_PATTERN.test(key) || key.startsWith('?');

const normalizeHookMap = (node: Record<string, unknown>): HookMap =>
  Object.fromEntries(
    Object.entries(HOOK_ALIASES)
      .map(([name, keys]) => [
        name,
        keys
          .map((key) => node[key])
          .find((value): value is string => typeof value === 'string'),
      ])
      .filter((entry): entry is [string, string] => typeof entry[1] === 'string'),
  ) as HookMap;

const hasMetadata = (node: Record<string, unknown>): boolean =>
  Object.keys(node).some((key) => !isChildKey(key));

const hasChildren = (node: Record<string, unknown>): boolean =>
  Object.keys(node).some((key) => isChildKey(key));

export const extractPagesFromPlan = (content: string): ExtractedPage[] => {
  if (content.trim() === '') {
    return [];
  }

  const parsed = yaml.load(content);
  if (!isRecord(parsed) || !isRecord(parsed.pages)) {
    return [];
  }

  const out = new Map<string, ExtractedPage>();

  const registerPage = (pagePath: string, hooks: HookMap, title: string, id: string): void => {
    out.set(pagePath, {
      id,
      path: pagePath,
      hooks: { ...hooks },
      title,
    });
  };

  const walk = (pagePath: string, node: PlanNode, inheritedHooks: HookMap = {}): void => {
    if (typeof node === 'string' || !isRecord(node)) {
      registerPage(pagePath, inheritedHooks, '', '');
      return;
    }

    const effectiveHooks = {
      ...inheritedHooks,
      ...normalizeHookMap(node),
    };
    const title = typeof node.title === 'string' ? node.title : '';
    const id = typeof node.id === 'string' ? node.id : '';

    if (hasMetadata(node) || !hasChildren(node)) {
      registerPage(pagePath, effectiveHooks, title, id);
    }

    for (const [key, child] of Object.entries(node)) {
      if (key === '') {
        walk(pagePath, child as PlanNode, effectiveHooks);
        continue;
      }

      if (key === '/') {
        walk(pagePath.endsWith('/') ? pagePath : `${pagePath}/`, child as PlanNode, effectiveHooks);
        continue;
      }

      if (key.startsWith('?')) {
        walk(`${pagePath}${key}`, child as PlanNode, effectiveHooks);
        continue;
      }

      if (key.startsWith('/')) {
        walk(pagePath === '/' ? key : `${pagePath}${key}`, child as PlanNode, effectiveHooks);
      }
    }
  };

  for (const [key, node] of Object.entries(parsed.pages)) {
    if (key.startsWith('/')) {
      walk(key, node as PlanNode);
    }
  }

  return [...out.values()].sort((left, right) => left.path.localeCompare(right.path));
};
