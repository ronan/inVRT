import process from 'node:process';
import chalk from 'chalk';

type LogLevel = 'error' | 'warning' | 'notice' | 'info' | 'debug';

const thresholds: Record<LogLevel, number> = {
  error: 0,
  warning: 0,
  notice: 0,
  info: 1,
  debug: 3,
};

export class Logger {
  public constructor(private readonly verbosity: number) {}

  public error(message: string): void {
    this.write('error', message, process.stderr);
  }

  public warning(message: string): void {
    this.write('warning', message);
  }

  public notice(message: string): void {
    this.write('notice', message);
  }

  public info(message: string): void {
    this.write('info', message);
  }

  public debug(message: string): void {
    this.write('debug', message);
  }

  public title(message: string): void {
    process.stdout.write(`${chalk.bold(message)}\n`);
  }

  public blankLine(): void {
    process.stdout.write('\n');
  }

  private write(level: LogLevel, message: string, stream: NodeJS.WriteStream = process.stdout): void {
    if (this.verbosity < thresholds[level]) {
      return;
    }

    const line = level === 'debug' ? `${chalk.dim('[debug]')} ${message}` : message;
    stream.write(`${line}\n`);
  }
}
