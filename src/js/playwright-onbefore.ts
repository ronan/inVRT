import log from './logger.js';

type Scenario = {
  label?: string;
  url?: string;
};

export default async (_page: unknown, scenario: Scenario): Promise<void> => {
  log.debug(`Capturing page: ${scenario.label ?? '(unknown)'}: ${scenario.url ?? '(unknown)'}`);
};
