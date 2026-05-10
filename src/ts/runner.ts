import fs from 'node:fs';
import path from 'node:path';
import { parse } from 'yaml';
import { z } from 'zod';

import { Configuration } from './configuration.js';
import { ensureDir, writeFile } from './filesystem.js';
import { Logger } from './logger.js';
import { updatePlan, hasPages } from './plan.js';
import { runNodeScript } from './process.js';
import { generateProjectId } from './project-id.js';
import { runPlaywright } from './playwright.js';

const checkOutputSchema = z.object({
  url: z.string().optional().default(''),
  title: z.string().optional().default(''),
  checked_at: z.string().optional().default(''),
});

const infoSchema = z.object({
  name: z.string().optional().default(''),
  id: z.string().optional().default(''),
  plan_file: z.string().optional().default(''),
  environment: z.string().optional().default(''),
  profile: z.string().optional().default(''),
  device: z.string().optional().default(''),
  environments: z.array(z.string()).optional().default([]),
  profiles: z.array(z.string()).optional().default([]),
  devices: z.array(z.string()).optional().default([]),
  planned_pages: z.number().optional().default(0),
  reference_screenshots: z.number().optional().default(0),
  test_screenshots: z.number().optional().default(0),
});

const DEFAULT_EXCLUDE_PATHS = [
  '/logout',
  '/user/logout',
  '/files',
  '/download',
  '/assets',
  '/images',
];

const configuredProfiles = (config: Configuration): string[] => {
  const profiles = config.getSection('profiles');
  if (Array.isArray(profiles)) {
    return profiles.map(String);
  }
  if (typeof profiles === 'object' && profiles !== null) {
    return Object.keys(profiles);
  }
  return [];
};

const normalizeUrl = (value: string): string => value.trim().replace(/\/$/u, '');

type TargetContext = {
  url: string;
  profile: string;
  device: string;
  environment: string;
};

export class Runner {
  public constructor(
    private readonly config: Configuration,
    private readonly appRoot: string,
    private readonly logger: Logger,
  ) {}

  public async init(url: string): Promise<number> {
    const cwd = this.config.get('INVRT_CWD');
    const directory = this.config.get('INVRT_DIRECTORY');
    const environment = this.config.get('INVRT_ENVIRONMENT');
    const profile = this.config.get('INVRT_PROFILE');
    const device = this.config.get('INVRT_DEVICE');
    const planFile = this.config.get('INVRT_PLAN_FILE');

    if (cwd === '') {
      this.logger.error("⚠️  I can't make a directory here because I don't know where I am.");
      return 1;
    }

    const normalizedUrl = normalizeUrl(url);
    if (normalizedUrl === '') {
      this.logger.error('A valid URL is required to initialize inVRT.');
      return 1;
    }

    if (fs.existsSync(directory)) {
      this.logger.error(`⚠️  InVRT is already initialized for this project. Please remove the .invrt directory (${directory}) if you want to re-initialize.`);
      return 1;
    }

    this.logger.notice(`🚀 Initializing InVRT for the project at ${cwd}`);

    ensureDir(directory);
    ensureDir(path.join(directory, 'data'));
    ensureDir(path.join(directory, 'scripts'));
    writeFile(
      path.join(directory, 'scripts', 'onready.ts'),
      "// Runs after the page is ready and before the screenshot is captured.\n",
    );

    this.logger.info(`✓ Created an invrt directory at: ${directory}`);

    const projectId = generateProjectId(normalizedUrl);
    const projectName = path.basename(cwd) || 'My InVRT Project';

    updatePlan(planFile, {
      url: normalizedUrl,
      id: projectId,
      name: projectName,
      environments: { [environment]: { url: normalizedUrl } },
      profiles: { [profile]: [] },
      devices: { [device]: [] },
      exclude: DEFAULT_EXCLUDE_PATHS,
    });

    this.logger.info(`✓ Initialized plan file at ${planFile}`);

    this.config.set('INVRT_URL', normalizedUrl);
    this.config.set('INVRT_ID', projectId);

    if (this.config.get('INVRT_LOGIN_URL') === '') {
      this.config.set('INVRT_LOGIN_URL', `${normalizedUrl}/user/login`);
    }

    if (this.config.get('INVRT_PLAN_FILE') === '') {
      this.config.set('INVRT_PLAN_FILE', planFile);
    }

    if ((await this.check()) !== 0) {
      this.logger.warning('⚠️  Site check failed. Run `invrt check` manually once the site is reachable.');
    }

    return 0;
  }

  public getConfig(): Record<string, string> {
    return this.config.all();
  }

  public async info(): Promise<z.infer<typeof infoSchema>> {
    const result = await this.runScript('info.js');

    if (result.exitCode !== 0) {
      return infoSchema.parse({});
    }

    return infoSchema.parse(JSON.parse(result.stdout));
  }

  public async check(): Promise<number> {
    const result = await this.runScript('check.js');

    if (result.exitCode !== 0) {
      return result.exitCode;
    }

    const parsed = parse(result.stdout);
    const data = checkOutputSchema.parse(typeof parsed === 'object' && parsed !== null ? parsed : {});

    updatePlan(this.config.get('INVRT_PLAN_FILE'), {
      url: data.url || this.config.get('INVRT_URL'),
      id: this.config.get('INVRT_ID'),
      title: data.title,
      home_title: data.title,
      checked_at: data.checked_at,
      profiles: configuredProfiles(this.config),
    });

    this.logger.debug('Updated plan.yaml with latest check metadata.');

    return 0;
  }

  public async crawl(): Promise<number> {
    const result = await this.runScript('crawl.js');

    const crawlFile = this.config.get('INVRT_CRAWL_FILE');
    if (crawlFile !== '') {
      writeFile(crawlFile, result.stdout);
    }

    if (result.exitCode !== 0) {
      return result.exitCode;
    }

    if (result.stdout.trim() === '') {
      return 0;
    }

    const buildResult = await this.runScript('build-plan-tree.js', { stdin: result.stdout });

    return buildResult.exitCode;
  }

  public async reference(): Promise<number> {
    this.logger.info(this.describeTarget('📸 Capturing references from'));

    if (!hasPages(this.config.get('INVRT_PLAN_FILE'))) {
      this.logger.notice('🕸️ No planned pages found — running crawl first.');
      const crawlResult = await this.crawl();
      if (crawlResult !== 0) {
        return crawlResult;
      }

      if (!hasPages(this.config.get('INVRT_PLAN_FILE'))) {
        this.logger.notice('No pages are available. Crawl has run but found no usable URLs.');
        return 1;
      }
    }

    const generateResult = await this.generatePlaywright();
    if (generateResult !== 0) {
      return generateResult;
    }

    return runPlaywright(this.config, this.logger, this.appRoot, 'reference');
  }

  public async test(): Promise<number> {
    this.logger.notice(this.describeTarget('🔬 Testing'));

    const referenceFile = this.config.get('INVRT_REFERENCE_FILE');
    if (!fs.existsSync(referenceFile)) {
      this.logger.notice('📸 No reference screenshots found — capturing references first.');
      const referenceResult = await this.reference();
      if (referenceResult !== 0) {
        return referenceResult;
      }
    } else {
      const generateResult = await this.generatePlaywright();
      if (generateResult !== 0) {
        return generateResult;
      }
    }

    return runPlaywright(this.config, this.logger, this.appRoot, 'test');
  }

  public async approve(): Promise<number> {
    this.logger.notice(this.describeTarget('✅ Approving latest results for'));

    return runPlaywright(this.config, this.logger, this.appRoot, 'reference');
  }

  public async baseline(): Promise<number> {
    for (const step of [this.check.bind(this), this.crawl.bind(this)]) {
      const result = await step();
      if (result !== 0) {
        return result;
      }
    }

    const generateResult = await this.generatePlaywright();
    if (generateResult !== 0) {
      return generateResult;
    }

    for (const step of [this.reference.bind(this), this.test.bind(this), this.approve.bind(this)]) {
      const result = await step();
      if (result !== 0) {
        return result;
      }
    }

    return 0;
  }

  public async configurePlaywright(): Promise<number> {
    const result = await this.runScript('configure-playwright.js');

    return result.exitCode;
  }

  public async generatePlaywright(): Promise<number> {
    const configureResult = await this.configurePlaywright();
    if (configureResult !== 0) {
      return configureResult;
    }

    const input = fs.readFileSync(this.config.get('INVRT_PLAN_FILE'), 'utf8');
    const result = await this.runScript('generate-playwright.js', {
      stdin: input,
      outputFile: this.config.get('INVRT_PLAYWRIGHT_SPEC_FILE'),
    });

    return result.exitCode;
  }

  public async login(): Promise<number> {
    const username = this.config.get('INVRT_USERNAME');
    const password = this.config.get('INVRT_PASSWORD');

    this.logger.debug(
      `Login pre-check (username=${username === '' ? 'no' : 'yes'}, has_password=${password === '' ? 'no' : 'yes'}, session_file=${this.config.get('INVRT_SESSION_FILE') || '(not set)'})`,
    );

    if (username === '' && password === '') {
      this.logger.debug('No credentials provided; skipping login.');
      return 0;
    }

    const url = this.config.get('INVRT_URL');
    if (url === '') {
      this.logger.error('❌ Profile has credentials but no URL configured. Cannot login.');
      return 1;
    }

    const loginUrl = this.config.get('INVRT_LOGIN_URL') || `${url}/user/login`;

    this.logger.notice('🔐 Logging in with provided credentials...');
    this.logger.debug(`Login URL: ${loginUrl}`);
    this.logger.debug(`Session output file: ${this.config.get('INVRT_SESSION_FILE')}`);

    const result = await this.runScript('playwright-login.js', {
      env: {
        INVRT_LOGIN_URL: loginUrl,
        INVRT_USERNAME: username,
        INVRT_PASSWORD: password,
        INVRT_SESSION_FILE: this.config.get('INVRT_SESSION_FILE'),
      },
    });

    this.logger.debug(`Login command exit code: ${result.exitCode}`);

    if (result.exitCode !== 0) {
      this.logger.error(`❌ Playwright login failed with exit code ${result.exitCode}`);
      return 1;
    }

    this.logger.notice('✅ Login successful!');
    return 0;
  }

  public async report(): Promise<number> {
    const directory = this.config.get('INVRT_DIRECTORY');
    if (directory === '') {
      this.logger.error('INVRT_DIRECTORY is not set; cannot write report.');
      return 1;
    }

    this.logger.notice('📝 Building report…');

    const result = await this.runScript('generate-report.js', {
      env: {
        ...this.config.all(),
        INVRT_VERSION: this.readVersion(),
      },
    });

    if (result.exitCode !== 0) {
      return result.exitCode;
    }

    this.logger.notice(`📝 Report written to ${directory}/index.html`);
    return 0;
  }

  private readVersion(): string {
    const pkg = JSON.parse(fs.readFileSync(path.join(this.appRoot, 'package.json'), 'utf8')) as { version?: string };
    return pkg.version ?? '';
  }

  private currentTarget(): TargetContext {
    return {
      url: this.config.get('INVRT_URL'),
      profile: this.config.get('INVRT_PROFILE'),
      device: this.config.get('INVRT_DEVICE'),
      environment: this.config.get('INVRT_ENVIRONMENT'),
    };
  }

  private describeTarget(prefix: string): string {
    const { url, profile, device, environment } = this.currentTarget();
    return `${prefix} '${environment}' environment (${url}) with profile: '${profile}' and device: '${device}'`;
  }

  private runScript(
    script: string,
    options: {
      env?: Record<string, string>;
      stdin?: string;
      outputFile?: string;
    } = {},
  ) {
    return runNodeScript({
      appRoot: this.appRoot,
      script,
      env: options.env ?? this.config.all(),
      stdin: options.stdin,
      outputFile: options.outputFile,
      logger: this.logger,
    });
  }
}
