import log from './logger.js';

type KeyPressSelector = {
  selector: string;
  keyPress: string;
};

type Scenario = {
  label?: string;
  pageTitle?: string;
  hoverSelectors?: string | string[];
  hoverSelector?: string | string[];
  clickSelectors?: string | string[];
  clickSelector?: string | string[];
  keyPressSelectors?: KeyPressSelector | KeyPressSelector[];
  keyPressSelector?: KeyPressSelector | KeyPressSelector[];
  scrollToSelector?: string;
  postInteractionWait?: string | number;
};

type PageLike = {
  title(): Promise<string>;
  waitForSelector(selector: string): Promise<void>;
  type(selector: string, keyPress: string): Promise<void>;
  hover(selector: string): Promise<void>;
  click(selector: string): Promise<void>;
  waitForTimeout(timeout: number): Promise<void>;
  evaluate<T>(fn: (selector: string) => T, selector: string): Promise<T>;
};

const asArray = <T>(value: T | T[] | undefined): T[] => {
  if (value === undefined) {
    return [];
  }

  return Array.isArray(value) ? value : [value];
};

const clickAndHoverHelper = async (page: PageLike, scenario: Scenario): Promise<void> => {
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
    } else {
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

export default async (page: PageLike, scenario: Scenario): Promise<void> => {
  const title = await page.title();
  scenario.pageTitle = title;
  log.debug(`Page ready: ${title} [${scenario.label ?? '(unknown)'}]`);
  await clickAndHoverHelper(page, scenario);
};
