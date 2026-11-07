import { mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const root = fileURLToPath(new URL('.', import.meta.url));
const sessions = fileURLToPath(new URL('.runtime/sessions/', import.meta.url));
await mkdir(sessions, { recursive: true });
const php = spawn('php', ['-d', `session.save_path=${sessions}`, '-S', '127.0.0.1:8016', '-t', root, fileURLToPath(new URL('router.php', import.meta.url))], {
  cwd: root, stdio: 'inherit', windowsHide: true,
});
php.on('error', error => { console.error(error.message); process.exitCode = 1; });
php.on('exit', code => { process.exitCode = code || 0; });
process.on('SIGINT', () => php.kill());
process.on('SIGTERM', () => php.kill());
