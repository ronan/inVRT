import process from 'node:process';
import chalk from 'chalk';
const thresholds = {
    error: 0,
    warning: 0,
    notice: 0,
    info: 1,
    debug: 3,
};
export class Logger {
    verbosity;
    constructor(verbosity) {
        this.verbosity = verbosity;
    }
    error(message) {
        this.write('error', message, process.stderr);
    }
    warning(message) {
        this.write('warning', message);
    }
    notice(message) {
        this.write('notice', message);
    }
    info(message) {
        this.write('info', message);
    }
    debug(message) {
        this.write('debug', message);
    }
    title(message) {
        process.stdout.write(`${chalk.bold(message)}\n`);
    }
    blankLine() {
        process.stdout.write('\n');
    }
    write(level, message, stream = process.stdout) {
        if (this.verbosity < thresholds[level]) {
            return;
        }
        const line = level === 'debug' ? `${chalk.dim('[debug]')} ${message}` : message;
        stream.write(`${line}\n`);
    }
}
