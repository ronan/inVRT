import os from 'node:os';
import path from 'node:path';
import Conf from 'conf';

type CliState = {
  lastRun?: {
    command: string;
    cwd: string;
    planFile: string;
    environment: string;
    profile: string;
    device: string;
  };
};

const store = new Conf<CliState>({
  projectName: 'invrt',
  configName: 'cli-state',
  cwd: path.join(os.tmpdir(), 'invrt-conf'),
});

export const recordLastRun = (state: NonNullable<CliState['lastRun']>): void => {
  store.set('lastRun', state);
};
