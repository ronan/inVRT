const { default: mod } = require("astro/zod");
const yaml = require('js-yaml');


const HOOK_ALIASES = {
  setup: ['setup', 'before'],
  onready: ['onready', 'ready'],
  teardown: ['teardown', 'after'],
};
const CHILD_KEY_PATTERN = /^$|^\/$|^\.|^\#/;

const isChildKey = (key) => CHILD_KEY_PATTERN.test(key) || key.startsWith('?');

const indentBlock = (content, spaces = 2) => content
  .split('\n')
  .map((line) => `${' '.repeat(spaces)}${line}`)
  .join('\n');

const normalizeHookMap = (node) => {
  if (!node || typeof node !== 'object' || Array.isArray(node)) {
    return {};
  }

  return Object.fromEntries(Object.entries(HOOK_ALIASES)
    .map(([name, keys]) => [name, keys.map((key) => node[key]).find((value) => typeof value === 'string')])
    .filter(([, value]) => typeof value === 'string'));
};

const hasMetadata = (node) => Object.keys(node).some((key) => !isChildKey(key));
const hasChildren = (node) => Object.keys(node).some((key) => isChildKey(key));

/** Extract testable URL paths and inherited hooks from plan.yaml pages map. */
const extractPagesFromPlan = (content) => {
  if (!content) {
    return [];
  }

  const out = new Map();

  const registerPage = (id, path, hooks, title) => {
    out.set(path, {
      id,
      path,
      hooks: { ...hooks },
      title: typeof title === 'string' ? title : '',
    });
  };

  const walk = (path, node) => {
    if (typeof path !== 'string') {
      return;
    }
    if (
      !path.startsWith('/') && 
      !path == '' && 
      !path == '.'
    ) {
      return;
    }

    if (typeof node === 'string') {
      out.set(path, {title:node})
      return;
    }
    page = node;
    page.path = path;

    if (!node || typeof node !== 'object' || Array.isArray(node)) {
      out.set(path, node);
      return;
    }

    node.hooks = {
      ...node.hooks,
      ...normalizeHookMap(node),
    };
    if (node.id) {
      out.set(path, node);
    }

    for (const [key, child] of Object.entries(node)) {

      if (key === '') {
        walk(path, child);
        continue;
      }

      if (key === '/') {
        const slashPath = path.endsWith('/') ? path : `${path}/`;
        walk(slashPath, child);
        continue;
      }

      if (key.startsWith('?')) {
        walk(`${path}${key}`, child);
        continue;
      }

      if (key.startsWith('/')){
        const childPath = path === '/' ? key : `${path}${key}`;
        walk(childPath, child);
      }
    }
  };

  const parsed = yaml.load(content);
  if (!parsed || typeof parsed !== 'object') {
    return [];
  }
  const pages = parsed.pages;

  if (!pages || typeof pages !== 'object' || Array.isArray(pages)) {
    return [];
  }
  for (const [key, node] of Object.entries(pages)) {
    walk(key, node);
  }

  return [...out.values()].sort((left, right) => left.path.localeCompare(right.path));
};

const extractProjectFromPlan = (content) => {
  plan = loadPlan(content);
  return plan?.project;
}

const loadPlan = (content) => {
 if (!content) {
    return [];
  }

  const parsed = yaml.load(content);
  if (!parsed || typeof parsed !== 'object') {
    return [];
  }
  return parsed;
}

module.exports = {
  pages: extractPagesFromPlan,
  project: extractProjectFromPlan,
};
