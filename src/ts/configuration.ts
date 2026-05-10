import fs from 'node:fs';
import { CONFIG_DEFAULTS } from './config-schema.js';
import { readPlan, type Plan } from './plan.js';

const stringifyEnvValue = (value: unknown): string => {
  if (typeof value === 'string') {
    return value;
  }

  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }

  return '';
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const asSection = (value: unknown): Record<string, unknown> =>
  isRecord(value) ? value : {};

const asProfileSection = (profiles: Plan['profiles'], profile: string): Record<string, unknown> => {
  if (!isRecord(profiles) || Array.isArray(profiles)) {
    return {};
  }

  return asSection(profiles[profile]);
};

export class Configuration {
  private readonly parsed: Plan;

  private readonly resolved: Record<string, string>;

  private readonly exists: boolean;

  public constructor(
    private readonly filepath: string,
    private readonly env: Record<string, string>,
  ) {
    this.exists = Boolean(filepath) && fs.existsSync(filepath);
    this.parsed = this.exists ? readPlan(filepath) : {};
    this.resolved = this.resolve();
  }

  public get(key: string, defaultValue = ''): string {
    return this.resolved[key] ?? defaultValue;
  }

  public set(key: string, value: string): void {
    this.resolved[key] = value;
  }

  public getSection(key: keyof Plan): unknown {
    return this.parsed[key];
  }

  public all(): Record<string, string> {
    return { ...this.resolved };
  }

  public exportToProcess(): void {
    for (const [key, value] of Object.entries(this.resolved)) {
      process.env[key] = value;
    }
  }

  public fileExists(): boolean {
    return this.exists;
  }

  public getFilepath(): string {
    return this.filepath;
  }

  private resolve(): Record<string, string> {
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

    const cleaned = Object.fromEntries(
      Object.entries(combined)
        .filter(([key]) => key.startsWith('INVRT_'))
        .map(([key, value]) => [key, stringifyEnvValue(value)]),
    );

    const interpolated = this.interpolate(cleaned);
    interpolated.INVRT_PLAN_FILE = this.filepath;

    return interpolated;
  }

  private buildDefaults(profile: string, environment: string, device: string): Record<string, unknown> {
    const base = this.asEnv(CONFIG_DEFAULTS);

    base.INVRT_PROFILE = profile;
    base.INVRT_ENVIRONMENT = environment;
    base.INVRT_DEVICE = device;
    base.INVRT_CWD = this.env.INVRT_CWD || process.cwd();
    base.INVRT_COOKIE = '';

    return base;
  }

  private asEnv(config: Record<string, unknown>): Record<string, unknown> {
    const out: Record<string, unknown> = {};

    for (const [key, value] of Object.entries(config)) {
      const envKey = key.startsWith('INVRT_') ? key : `INVRT_${key.toUpperCase()}`;
      out[envKey] = value;
    }

    return out;
  }

  private interpolate(config: Record<string, string>): Record<string, string> {
    let next = { ...config };

    for (let i = 0; i < 3; i += 1) {
      next = Object.fromEntries(
        Object.entries(next).map(([key, value]) => {
          let resolved = value;
          for (const [token, replacement] of Object.entries(next)) {
            resolved = resolved.split(token).join(replacement);
          }
          return [key, resolved];
        }),
      );
    }

    return next;
  }
}
