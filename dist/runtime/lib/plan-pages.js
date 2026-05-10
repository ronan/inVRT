import yaml from 'js-yaml';
const HOOK_ALIASES = {
    setup: ['setup', 'before'],
    onready: ['onready', 'ready'],
    teardown: ['teardown', 'after'],
};
const CHILD_KEY_PATTERN = /^$|^\/$|^\//u;
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const isChildKey = (key) => CHILD_KEY_PATTERN.test(key) || key.startsWith('?');
const normalizeHookMap = (node) => Object.fromEntries(Object.entries(HOOK_ALIASES)
    .map(([name, keys]) => [
    name,
    keys
        .map((key) => node[key])
        .find((value) => typeof value === 'string'),
])
    .filter((entry) => typeof entry[1] === 'string'));
const hasMetadata = (node) => Object.keys(node).some((key) => !isChildKey(key));
const hasChildren = (node) => Object.keys(node).some((key) => isChildKey(key));
export const extractPagesFromPlan = (content) => {
    if (content.trim() === '') {
        return [];
    }
    const parsed = yaml.load(content);
    if (!isRecord(parsed) || !isRecord(parsed.pages)) {
        return [];
    }
    const out = new Map();
    const registerPage = (pagePath, hooks, title, id) => {
        out.set(pagePath, {
            id,
            path: pagePath,
            hooks: { ...hooks },
            title,
        });
    };
    const walk = (pagePath, node, inheritedHooks = {}) => {
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
                walk(pagePath, child, effectiveHooks);
                continue;
            }
            if (key === '/') {
                walk(pagePath.endsWith('/') ? pagePath : `${pagePath}/`, child, effectiveHooks);
                continue;
            }
            if (key.startsWith('?')) {
                walk(`${pagePath}${key}`, child, effectiveHooks);
                continue;
            }
            if (key.startsWith('/')) {
                walk(pagePath === '/' ? key : `${pagePath}${key}`, child, effectiveHooks);
            }
        }
    };
    for (const [key, node] of Object.entries(parsed.pages)) {
        if (key.startsWith('/')) {
            walk(key, node);
        }
    }
    return [...out.values()].sort((left, right) => left.path.localeCompare(right.path));
};
