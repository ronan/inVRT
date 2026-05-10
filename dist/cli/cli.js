import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import readline from 'node:readline/promises';
import { Command } from 'commander';
import { z } from 'zod';
import { Configuration } from './configuration.js';
import { CliError } from './errors.js';
import { Logger } from './logger.js';
import { Runner } from './runner.js';
import { recordLastRun } from './state.js';
const selectionSchema = z.object({
    environment: z.string().min(1).default('local'),
    profile: z.string().min(1).default('anonymous'),
    device: z.string().min(1).default('desktop'),
});
const initSchema = selectionSchema.extend({
    skipBaseline: z.boolean().default(false),
});
const appRoot = process.env.INVRT_APP_ROOT || process.cwd();
const packageJson = JSON.parse(fs.readFileSync(path.join(appRoot, 'package.json'), 'utf8'));
const PLAN_DIRS = ['invrt', '.invrt', '.ddev/.invrt', '.ddev/invrt'];
const DEFAULT_PLAN_DIR = '.invrt';
const countVerbosity = (argv) => argv.reduce((count, arg) => {
    if (arg === '--verbose' || arg === '-v') {
        return count + 1;
    }
    if (/^-v{2,}$/u.test(arg)) {
        return count + (arg.length - 1);
    }
    return count;
}, 0);
const stripVerbosity = (argv) => argv.filter((arg) => !/^--verbose$/u.test(arg) && !/^-v+$/u.test(arg));
const withSelectionOptions = (command) => command
    .option('-e, --environment <environment>', 'Environment name', 'local')
    .option('-p, --profile <profile>', 'Profile name', 'anonymous')
    .option('-d, --device <device>', 'Device type', 'desktop');
const resolveConfigFilepath = (env) => {
    if (env.INVRT_PLAN_FILE) {
        return env.INVRT_PLAN_FILE;
    }
    const cwd = env.INVRT_CWD || process.cwd();
    if (env.INVRT_DIRECTORY) {
        return path.join(env.INVRT_DIRECTORY, 'plan.yaml');
    }
    for (const dir of PLAN_DIRS) {
        const candidate = path.join(cwd, dir, 'plan.yaml');
        if (fs.existsSync(candidate)) {
            return candidate;
        }
    }
    return path.join(cwd, DEFAULT_PLAN_DIR, 'plan.yaml');
};
const processInvrtEnv = () => Object.fromEntries(Object.entries(process.env)
    .filter(([key, value]) => key.startsWith('INVRT_') && value !== undefined && value !== '')
    .map(([key, value]) => [key, value ?? '']));
const boot = async (commandName, selection, logger, requiresConfig = true, requiresLogin = true) => {
    logger.debug(`Bootstrapping command (environment=${selection.environment}, profile=${selection.profile}, device=${selection.device})`);
    const env = {
        INVRT_ENVIRONMENT: selection.environment,
        INVRT_PROFILE: selection.profile,
        INVRT_DEVICE: selection.device,
        INVRT_CWD: process.env.INVRT_CWD || process.cwd(),
    };
    const processEnv = processInvrtEnv();
    const filepath = resolveConfigFilepath({ ...processEnv, ...env });
    env.INVRT_DIRECTORY = path.dirname(filepath);
    let config;
    try {
        config = new Configuration(filepath, { ...processEnv, ...env });
    }
    catch (error) {
        logger.error(`# Error reading config file at: \`${filepath}\``);
        logger.debug(`Config read exception: ${error.message}`);
        throw new CliError('boot-failed');
    }
    if (requiresConfig && !config.fileExists()) {
        logger.error(`# Configuration file not found at: ${filepath}`);
        logger.error("# Run 'invrt init' to create a new configuration.");
        throw new CliError('missing-config');
    }
    config.exportToProcess();
    logger.debug(`Resolved config (config: ${config.get('INVRT_PLAN_FILE', '(not set)')}, url: ${config.get('INVRT_URL', '(not set)')})`);
    const runner = new Runner(config, appRoot, logger);
    recordLastRun({
        command: commandName,
        cwd: env.INVRT_CWD,
        planFile: config.get('INVRT_PLAN_FILE'),
        environment: selection.environment,
        profile: selection.profile,
        device: selection.device,
    });
    if (requiresLogin && (config.fileExists() || requiresConfig)) {
        const loginResult = await runner.login();
        if (loginResult !== 0) {
            throw new CliError('login-failed');
        }
    }
    return { config, runner, logger };
};
const canPromptForUrl = () => Boolean(process.stdin.isTTY);
const resolveInitUrl = async (config, logger, candidate) => {
    const raw = (candidate ?? config.get('INVRT_URL')).trim();
    if (raw !== '') {
        return z.string().url().parse(raw).replace(/\/$/u, '');
    }
    if (!canPromptForUrl()) {
        logger.error('A URL is required to initialize inVRT.');
        throw new CliError('missing-url');
    }
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
    });
    const answer = (await rl.question('What URL should inVRT use? ')).trim();
    await rl.close();
    if (answer === '') {
        logger.error('A URL is required to initialize inVRT.');
        throw new CliError('missing-url');
    }
    return z.string().url().parse(answer).replace(/\/$/u, '');
};
const bootOrInitialize = async (commandName, selection, logger, url) => {
    const context = await boot(commandName, selection, logger, false, true);
    if (context.config.fileExists()) {
        return context;
    }
    logger.notice('No configuration file found. Initializing inVRT first.');
    const resolvedUrl = await resolveInitUrl(context.config, logger, url);
    const initResult = await context.runner.init(resolvedUrl);
    if (initResult !== 0) {
        throw new CliError('init-failed');
    }
    return boot(commandName, selection, logger, true, true);
};
const printConfig = (config) => {
    process.stdout.write('# Current inVRT Configuration:\n\n');
    for (const [key, value] of Object.entries(config)) {
        process.stdout.write(`${key}: ${value}\n`);
    }
};
const printInfo = (info, logger) => {
    logger.title(info.name || 'inVRT Project');
    process.stdout.write(`${info.plan_file}\n\n`);
    process.stdout.write(`Project ID: ${info.id || '(not set)'}\n`);
    process.stdout.write(`Environment: ${info.environment}\n`);
    process.stdout.write(`Profile: ${info.profile}\n`);
    process.stdout.write(`Device: ${info.device}\n`);
    process.stdout.write(`Environments: ${info.environments.join(', ') || '(none)'}\n`);
    process.stdout.write(`Profiles: ${info.profiles.join(', ') || '(none)'}\n`);
    process.stdout.write(`Devices: ${info.devices.join(', ') || '(none)'}\n`);
    process.stdout.write(`Planned pages: ${info.planned_pages}\n`);
    process.stdout.write(`Reference screenshots: ${info.reference_screenshots}\n`);
    process.stdout.write(`Test screenshots: ${info.test_screenshots}\n`);
};
const printCommandList = () => {
    process.stdout.write([
        'init',
        'approve',
        'baseline',
        'check',
        'crawl',
        'reference',
        'test',
        'config',
        'info',
        'report',
    ].join('\n'));
    process.stdout.write('\n');
};
const registerSelectionCommand = (program, logger, options) => {
    withSelectionOptions(program.command(options.name).description(options.description))
        .action(async (rawOptions) => {
        const selection = selectionSchema.parse(rawOptions);
        const context = await (options.bootMode === 'boot-or-init'
            ? bootOrInitialize(options.name, selection, logger)
            : boot(options.name, selection, logger, options.requiresConfig ?? true, options.requiresLogin ?? false));
        const result = await options.action(context);
        if (typeof result === 'number') {
            process.exitCode = result;
        }
    });
};
const run = async () => {
    const rawArgs = process.argv.slice(2);
    const verbosity = countVerbosity(rawArgs);
    const argv = stripVerbosity(rawArgs);
    const logger = new Logger(verbosity);
    const program = new Command();
    program
        .name('invrt')
        .version(packageJson.version)
        .helpCommand(true)
        .showHelpAfterError();
    program.command('list').description('List commands').action(() => {
        printCommandList();
    });
    registerSelectionCommand(program, logger, {
        name: 'config',
        description: 'View the inVRT configuration',
        action: async ({ runner }) => {
            printConfig(runner.getConfig());
        },
    });
    registerSelectionCommand(program, logger, {
        name: 'info',
        description: 'Show project status summary',
        action: async ({ runner }) => {
            printInfo(await runner.info(), logger);
        },
    });
    registerSelectionCommand(program, logger, {
        name: 'check',
        description: 'Check site connectivity and collect metadata',
        action: ({ runner }) => runner.check(),
    });
    registerSelectionCommand(program, logger, {
        name: 'approve',
        description: 'Approve the latest visual test results',
        action: ({ runner }) => runner.approve(),
    });
    registerSelectionCommand(program, logger, {
        name: 'crawl',
        description: 'Crawl the website and generate screenshots',
        bootMode: 'boot-or-init',
        requiresLogin: true,
        action: ({ runner }) => runner.crawl(),
    });
    registerSelectionCommand(program, logger, {
        name: 'reference',
        description: 'Create reference screenshots for comparison',
        bootMode: 'boot-or-init',
        requiresLogin: true,
        action: ({ runner }) => runner.reference(),
    });
    registerSelectionCommand(program, logger, {
        name: 'test',
        description: 'Run visual regression tests',
        bootMode: 'boot-or-init',
        requiresLogin: true,
        action: ({ runner }) => runner.test(),
    });
    registerSelectionCommand(program, logger, {
        name: 'baseline',
        description: 'Capture a fresh baseline from check through approve',
        bootMode: 'boot-or-init',
        requiresLogin: true,
        action: ({ runner }) => runner.baseline(),
    });
    registerSelectionCommand(program, logger, {
        name: 'generate-playwright',
        description: 'Generate a Playwright spec from crawled URLs',
        action: ({ runner }) => runner.generatePlaywright(),
    });
    registerSelectionCommand(program, logger, {
        name: 'configure-playwright',
        description: 'Write the Playwright configuration file',
        action: ({ runner }) => runner.configurePlaywright(),
    });
    registerSelectionCommand(program, logger, {
        name: 'report',
        description: 'Build the HTML report from the latest test results',
        action: ({ runner }) => runner.report(),
    });
    withSelectionOptions(program.command('init')
        .description('Initialize a new inVRT project in the current directory')
        .argument('[url]', 'Website URL to save in the new config file')
        .option('--skip-baseline', 'Skip running baseline after init', false)).action(async (url, options) => {
        const selection = initSchema.parse(options);
        const context = await boot('init', selection, logger, false, false);
        const resolvedUrl = await resolveInitUrl(context.config, logger, typeof url === 'string' ? url : undefined);
        const initResult = await context.runner.init(resolvedUrl);
        if (initResult !== 0) {
            process.exitCode = initResult;
            return;
        }
        logger.notice('InVRT successfully initialized!');
        if (selection.skipBaseline) {
            process.exitCode = 0;
            return;
        }
        const rebooted = await boot('init', selection, logger, true, false);
        logger.notice('🏁 Running baseline to capture initial screenshots...');
        process.exitCode = await rebooted.runner.baseline();
    });
    await program.parseAsync(argv, { from: 'user' });
};
run().catch((error) => {
    if (error instanceof CliError) {
        process.exitCode = 1;
        return;
    }
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
});
