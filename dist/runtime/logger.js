import pino from 'pino';
const log = pino({
    level: 'trace',
    base: null,
    timestamp: false,
}, process.stderr);
export default log;
