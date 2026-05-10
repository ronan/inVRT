import fs from 'node:fs';
import path from 'node:path';
import { execa } from 'execa';
import { writeFile } from './filesystem.js';
const parseNodeLogs = (logger, stderr) => {
    let messages = '';
    for (const rawLine of stderr.split(/\r?\n/u)) {
        const line = rawLine.trim();
        if (line === '') {
            continue;
        }
        let parsed;
        try {
            parsed = JSON.parse(line);
        }
        catch {
            logger.debug(line);
            continue;
        }
        const msg = typeof parsed.msg === 'string' ? parsed.msg : line;
        const level = typeof parsed.level === 'number' ? parsed.level : 30;
        if (level >= 50) {
            logger.error(msg);
        }
        else if (level >= 40) {
            logger.warning(msg);
        }
        else if (level >= 30) {
            logger.notice(msg);
        }
        else {
            logger.debug(msg);
        }
        if (level >= 30) {
            messages += `${msg}\n`;
        }
    }
    return messages;
};
export const runProcess = async (options) => {
    options.logger.debug(`${options.debugLabel}: ${[options.command, ...options.args].join(' ')}`);
    const result = await execa(options.command, options.args, {
        cwd: options.cwd,
        env: options.env,
        input: options.input,
        reject: false,
        maxBuffer: 1024 * 1024 * 100,
    });
    if (options.outputFile) {
        options.logger.debug(`Writing output to ${options.outputFile}`);
        writeFile(options.outputFile, options.stdoutFileContents ? options.stdoutFileContents(result.stdout, result.stderr) : result.stdout);
    }
    return {
        exitCode: result.exitCode ?? 0,
        stdout: result.stdout,
        stderr: result.stderr,
    };
};
export const runNodeScript = async (options) => {
    const scriptPath = path.join(options.appRoot, 'dist/runtime', options.script);
    const input = options.stdin ?? (options.inputFile && fs.existsSync(options.inputFile)
        ? fs.readFileSync(options.inputFile, 'utf8')
        : undefined);
    const result = await runProcess({
        command: 'node',
        args: [scriptPath],
        cwd: options.cwd ?? options.appRoot,
        env: options.env,
        input,
        outputFile: options.outputFile,
        logger: options.logger,
        debugLabel: 'Running Node script',
    });
    parseNodeLogs(options.logger, result.stderr);
    return result;
};
