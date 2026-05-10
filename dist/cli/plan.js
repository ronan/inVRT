import fs from 'node:fs';
import path from 'node:path';
import { parse, stringify } from 'yaml';
import { z } from 'zod';
const planSchema = z.object({
    project: z.record(z.string(), z.unknown()).optional(),
    environments: z.record(z.string(), z.unknown()).optional(),
    profiles: z.union([z.array(z.string()), z.record(z.string(), z.unknown())]).optional(),
    devices: z.record(z.string(), z.unknown()).optional(),
    exclude: z.array(z.string()).optional(),
    pages: z.record(z.string(), z.unknown()).optional(),
}).catchall(z.unknown());
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const isList = (value) => Array.isArray(value);
const ensureRecord = (value) => (isRecord(value) ? value : {});
const mergeNamedSection = (current, next) => ({
    ...next,
    ...ensureRecord(current),
});
const orderTopLevel = (plan) => {
    const order = ['project', 'environments', 'profiles', 'devices', 'exclude', 'pages'];
    const sorted = {};
    const rest = { ...plan };
    for (const key of order) {
        if (key in rest) {
            sorted[key] = rest[key];
            delete rest[key];
        }
    }
    return { ...sorted, ...rest };
};
export const readPlan = (planFile) => {
    if (!fs.existsSync(planFile) || fs.statSync(planFile).size === 0) {
        return {};
    }
    const parsed = parse(fs.readFileSync(planFile, 'utf8'));
    if (!isRecord(parsed)) {
        return {};
    }
    return planSchema.parse(parsed);
};
export const writePlan = (planFile, plan) => {
    fs.mkdirSync(path.dirname(planFile), { recursive: true });
    fs.writeFileSync(planFile, stringify(orderTopLevel(plan), { lineWidth: 0 }));
};
export const hasPages = (planFile) => {
    if (planFile === '' || !fs.existsSync(planFile)) {
        return false;
    }
    const pages = readPlan(planFile).pages;
    if (!isRecord(pages)) {
        return false;
    }
    return Object.keys(pages).some((key) => key.startsWith('/'));
};
export const updatePlan = (planFile, data) => {
    const plan = readPlan(planFile);
    plan.project = ensureRecord(plan.project);
    plan.pages = ensureRecord(plan.pages);
    for (const key of ['url', 'id', 'name', 'title', 'checked_at']) {
        const value = (data[key] ?? '').trim();
        if (value !== '') {
            plan.project[key] = value;
        }
    }
    if (data.profiles !== undefined) {
        const existing = plan.profiles;
        if (isList(data.profiles)) {
            if (existing === undefined || isList(existing)) {
                const merged = [
                    ...(isList(existing) ? existing.map(String) : []),
                    ...data.profiles.map(String),
                ];
                plan.profiles = [...new Set(merged)];
            }
            else if (isRecord(existing)) {
                const merged = { ...ensureRecord(existing) };
                for (const name of data.profiles) {
                    merged[String(name)] ??= {};
                }
                plan.profiles = merged;
            }
        }
        else if (isRecord(data.profiles)) {
            plan.profiles = isRecord(existing) && !isList(existing)
                ? { ...data.profiles, ...existing }
                : data.profiles;
        }
    }
    for (const section of ['environments', 'devices']) {
        const next = data[section];
        if (!isRecord(next)) {
            continue;
        }
        plan[section] = mergeNamedSection(plan[section], next);
    }
    if (Array.isArray(data.exclude)) {
        const existing = plan.exclude;
        if (!Array.isArray(existing) || existing.length === 0) {
            plan.exclude = [...data.exclude];
        }
    }
    const pages = plan.pages;
    if (!('/' in pages)) {
        pages['/'] = [];
    }
    const homeTitle = (data.home_title ?? '').trim();
    if (homeTitle !== '') {
        const home = pages['/'];
        pages['/'] = isRecord(home) ? { ...home, title: homeTitle } : { title: homeTitle };
    }
    writePlan(planFile, plan);
    return true;
};
