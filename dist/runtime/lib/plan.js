import yaml from 'js-yaml';
import { extractPagesFromPlan } from './plan-pages.js';
const isRecord = (value) => typeof value === 'object' && value !== null && !Array.isArray(value);
const loadPlan = (content) => {
    if (content.trim() === '') {
        return {};
    }
    const parsed = yaml.load(content);
    return isRecord(parsed) ? parsed : {};
};
export const pages = (content) => extractPagesFromPlan(content);
export const project = (content) => loadPlan(content).project;
