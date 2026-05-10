import log from './logger.js';
const asArray = (value) => {
    if (value === undefined) {
        return [];
    }
    return Array.isArray(value) ? value : [value];
};
const clickAndHoverHelper = async (page, scenario) => {
    const hoverSelector = scenario.hoverSelectors ?? scenario.hoverSelector;
    const clickSelector = scenario.clickSelectors ?? scenario.clickSelector;
    const keyPressSelector = scenario.keyPressSelectors ?? scenario.keyPressSelector;
    const scrollToSelector = scenario.scrollToSelector;
    const postInteractionWait = scenario.postInteractionWait;
    for (const item of asArray(keyPressSelector)) {
        await page.waitForSelector(item.selector);
        await page.type(item.selector, item.keyPress);
    }
    for (const selector of asArray(hoverSelector)) {
        await page.waitForSelector(selector);
        await page.hover(selector);
    }
    for (const selector of asArray(clickSelector)) {
        await page.waitForSelector(selector);
        await page.click(selector);
    }
    if (postInteractionWait !== undefined) {
        const parsed = Number.parseInt(String(postInteractionWait), 10);
        if (parsed > 0) {
            await page.waitForTimeout(parsed);
        }
        else {
            await page.waitForSelector(String(postInteractionWait));
        }
    }
    if (scrollToSelector) {
        await page.waitForSelector(scrollToSelector);
        await page.evaluate((selector) => {
            document.querySelector(selector)?.scrollIntoView();
        }, scrollToSelector);
    }
};
export default async (page, scenario) => {
    const title = await page.title();
    scenario.pageTitle = title;
    log.debug(`Page ready: ${title} [${scenario.label ?? '(unknown)'}]`);
    await clickAndHoverHelper(page, scenario);
};
