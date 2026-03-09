import fs from 'fs';
import path from 'path';
import { exec } from 'child_process';
import yaml from 'js-yaml';

const invrtDirectory = path.join(process.env.INIT_CWD || process.cwd(), 'invrt');
const configFile = path.join(invrtDirectory, 'config.yaml');

let config = {};
try {
  const configContent = fs.readFileSync(configFile, 'utf8');
  config = yaml.load(configContent) || {};
} catch (error) {
  console.error(`Error reading config file: ${error.message}`);
  process.exit(1);
}

const invrtUrl = config?.project?.url || '';
const invrtDepthToCrawl = config?.settings?.max_crawl_depth || 1;
const logFile = path.join(invrtDirectory, 'data', 'logs', 'crawl.log');
const clonesDir = path.join(invrtDirectory, 'data', 'clones');
const outputFile = path.join(invrtDirectory, 'data', 'crawled_urls.txt');

console.log(`🕸️ Crawling ${invrtUrl} to depth ${invrtDepthToCrawl}`);

const command = `cd ${clonesDir} && wget --level=${invrtDepthToCrawl} --spider --recursive --force-html --max-redirect=2 --user-agent=invrt/crawler --exclude-directories=/sites/default/files --execute robots=off ${invrtUrl} 2>&1 | tee -a ${logFile} | grep -B 3 "[text/html]" | grep ${invrtUrl} | awk '/--/{gsub("${invrtUrl}", "", $3); print $3}' | sort | uniq > ../../crawled_urls.txt`;

exec(command, (error, stdout, stderr) => {
  if (error) {
    console.error(`Error: ${error.message}`);
    process.exit(1);
  }
  console.log('Crawling completed. Results saved to ./invrt/data/crawled_urls.txt');
});