import path from 'node:path';
import { runProcess } from './process.js';
const splitOutputLines = (value) => value.split(/\r?\n/u).map((line) => line.trim()).filter((line) => line !== '');
export const runPlaywright = async (config, logger, appRoot, mode) => {
    const configFile = config.get('INVRT_PLAYWRIGHT_CONFIG_FILE');
    const specFile = config.get('INVRT_PLAYWRIGHT_SPEC_FILE');
    const workingDir = configFile !== '' ? path.dirname(configFile) : config.get('INVRT_DIRECTORY', appRoot);
    const binary = path.join(appRoot, 'node_modules', '.bin', 'playwright');
    const args = ['test'];
    if (configFile !== '') {
        args.push(`--config=${configFile}`);
    }
    if (specFile !== '') {
        args.push(specFile);
    }
    if (mode === 'reference') {
        args.push('--update-snapshots=all');
    }
    logger.debug(`Running Playwright command: ${[binary, ...args].join(' ')}`);
    logger.notice(`Running playwright test${mode === 'reference' ? ' --update-snapshots' : ''}`);
    const env = {
        ...config.all(),
        NODE_PATH: path.join(appRoot, 'node_modules'),
        INVRT_RESULTS_FILE: path.join(config.get('INVRT_DIRECTORY'), 'report.json'),
    };
    const file = mode === 'reference'
        ? config.get('INVRT_REFERENCE_FILE')
        : config.get('INVRT_TEST_FILE');
    const result = await runProcess({
        command: binary,
        args,
        cwd: workingDir,
        env,
        logger,
        debugLabel: 'Running Playwright command',
        outputFile: file !== '' ? file : undefined,
        stdoutFileContents: (stdout, stderr) => [stdout, stderr].filter(Boolean).join('\n'),
    });
    for (const line of splitOutputLines(result.stdout)) {
        logger.notice(line);
    }
    for (const line of splitOutputLines(result.stderr)) {
        logger.notice(line);
    }
    const exitCode = result.exitCode ?? 0;
    logger.debug(`Playwright exit code: ${exitCode}`);
    return exitCode;
};
