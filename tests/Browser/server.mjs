import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const database = process.env.DB_DATABASE;
const allowedDirectory = resolve('storage/framework/testing');
if (
    !database ||
    !resolve(database).startsWith(
        allowedDirectory + '/'.replace('/', process.platform === 'win32' ? '\\' : '/'),
    ) ||
    process.env.DB_CONNECTION !== 'sqlite'
) {
    throw new Error(
        'Browser tests require an isolated SQLite file under storage/framework/testing.',
    );
}
mkdirSync(dirname(database), { recursive: true });
writeFileSync(database, '', { flag: 'wx' });
for (const args of [
    ['artisan', 'migrate', '--no-interaction'],
    ['artisan', 'db:seed', '--class=DemoSeeder', '--no-interaction'],
]) {
    const result = spawnSync('php', args, {
        env: process.env,
        stdio: 'inherit',
        windowsHide: true,
    });
    if (result.status !== 0) process.exit(result.status ?? 1);
}
const server = spawn(
    'php',
    ['artisan', 'serve', '--host=127.0.0.1', '--port=8013', '--no-reload'],
    { env: process.env, stdio: 'inherit', windowsHide: true },
);
server.on('exit', (code) => process.exit(code ?? 0));
for (const signal of ['SIGINT', 'SIGTERM'])
    process.on(signal, () => {
        server.kill();
    });
