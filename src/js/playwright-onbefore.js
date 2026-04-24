const log = require('./logger');

module.exports = async (page, scenario, viewport, isReference, browserContext) => {
  log.debug(`Capturing page: ${scenario.label}: ${scenario.url}`);
};
