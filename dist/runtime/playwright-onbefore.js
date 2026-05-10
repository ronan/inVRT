import log from './logger.js';
export default async (_page, scenario) => {
    log.debug(`Capturing page: ${scenario.label ?? '(unknown)'}: ${scenario.url ?? '(unknown)'}`);
};
