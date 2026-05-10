export class CliError extends Error {
  public constructor(
    public readonly reason: 'boot-failed' | 'missing-config' | 'login-failed' | 'missing-url' | 'init-failed',
    message = reason,
  ) {
    super(message);
  }
}
