module.exports = async (page, scenario, viewport, isReference, browserContext) => {
  console.log('Running onBefore script with scenario:', scenario.name);
  await require('./loadCookies')(browserContext, scenario);
};
