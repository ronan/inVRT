import fs from 'node:fs';
import { CONFIG_DEFAULTS } from './config-schema.js';
import { readPlan } from './plan.js';
const stringifyEnvValue = (value) => {
    if (typeof value === 'string') {
        return value;
    }
    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }
    return '';
};
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const asSection = (value) => isRecord(value) ? value : {};
const asProfileSection = (profiles, profile) => {
    if (!isRecord(profiles) || Array.isArray(profiles)) {
        return {};
    }
    return asSection(profiles[profile]);
};
export class Configuration {
    filepath;
    env;
    parsed;
    resolved;
    exists;
    constructor(filepath, env) {
        this.filepath = filepath;
        this.env = env;
        this.exists = Boolean(filepath) && fs.existsSync(filepath);
        this.parsed = this.exists ? readPlan(filepath) : {};
        this.resolved = this.resolve();
    }
    get(key, defaultValue = '') {
        return this.resolved[key] ?? defaultValue;
    }
    set(key, value) {
        this.resolved[key] = value;
    }
    getSection(key) {
        return this.parsed[key];
    }
    all() {
        return { ...this.resolved };
    }
    exportToProcess() {
        for (const [key, value] of Object.entries(this.resolved)) {
            process.env[key] = value;
        }
    }
    fileExists() {
        return this.exists;
    }
    getFilepath() {
        return this.filepath;
    }
    resolve() {
        const profile = this.env.INVRT_PROFILE || 'anonymous';
        const environment = this.env.INVRT_ENVIRONMENT || 'local';
        const device = this.env.INVRT_DEVICE || 'desktop';
        const base = this.buildDefaults(profile, environment, device);
        const project = { ...asSection(this.parsed.project) };
        delete project.name;
        const settings = this.asEnv(project);
        const envSection = this.asEnv(asSection(this.parsed.environments?.[environment]));
        const profileSection = this.asEnv(asProfileSection(this.parsed.profiles, profile));
        const deviceSection = this.asEnv(asSection(this.parsed.devices?.[device]));
        const combined = {
            ...base,
            ...settings,
            ...envSection,
            ...profileSection,
            ...deviceSection,
            ...this.env,
        };
        const cleaned = Object.fromEntries(Object.entries(combined)
            .filter(([key]) => key.startsWith('INVRT_'))
            .map(([key, value]) => [key, stringifyEnvValue(value)]));
        const interpolated = this.interpolate(cleaned);
        interpolated.INVRT_PLAN_FILE = this.filepath;
        return interpolated;
    }
    buildDefaults(profile, environment, device) {
        const base = this.asEnv(CONFIG_DEFAULTS);
        base.INVRT_PROFILE = profile;
        base.INVRT_ENVIRONMENT = environment;
        base.INVRT_DEVICE = device;
        base.INVRT_CWD = this.env.INVRT_CWD || process.cwd();
        base.INVRT_COOKIE = '';
        return base;
    }
    asEnv(config) {
        const out = {};
        for (const [key, value] of Object.entries(config)) {
            const envKey = key.startsWith('INVRT_') ? key : `INVRT_${key.toUpperCase()}`;
            out[envKey] = value;
        }
        return out;
    }
    interpolate(config) {
        let next = { ...config };
        for (let i = 0; i < 3; i += 1) {
            next = Object.fromEntries(Object.entries(next).map(([key, value]) => {
                let resolved = value;
                for (const [token, replacement] of Object.entries(next)) {
                    resolved = resolved.split(token).join(replacement);
                }
                return [key, resolved];
            }));
        }
        return next;
    }
}
