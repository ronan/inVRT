import os from 'node:os';
import path from 'node:path';
import Conf from 'conf';
const store = new Conf({
    projectName: 'invrt',
    configName: 'cli-state',
    cwd: path.join(os.tmpdir(), 'invrt-conf'),
});
export const recordLastRun = (state) => {
    store.set('lastRun', state);
};
