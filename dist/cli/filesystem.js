import fs from 'node:fs';
import path from 'node:path';
export const ensureDir = (dir) => {
    if (dir === '' || fs.existsSync(dir)) {
        return;
    }
    fs.mkdirSync(dir, { recursive: true });
};
export const writeFile = (file, contents) => {
    ensureDir(path.dirname(file));
    fs.writeFileSync(file, contents);
};
