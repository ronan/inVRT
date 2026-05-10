export class CliError extends Error {
    reason;
    constructor(reason, message = reason) {
        super(message);
        this.reason = reason;
    }
}
