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
const invrtProfile = process.env.INVRT_PROFILE || 'default';
const invrtDevice = process.env.INVRT_DEVICE || 'desktop';

console.log(`📸 Capturing references from ${invrtUrl} with profile ${invrtProfile} with device ${invrtDevice} to depth ${invrtDepthToCrawl}`);

const runJsPath = path.join(process.cwd(), 'src', 'run.js');
exec(`node ${runJsPath} reference`, (error, stdout, stderr) => {
  if (error) {
    console.error(`Error: ${error.message}`);
    process.exit(1);
  }
  if (stdout) console.log(stdout);
  if (stderr) console.error(stderr);
});