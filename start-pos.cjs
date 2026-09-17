// Local development launcher. Does not change configuration or database contents.
const net = require('node:net');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const crypto = require('node:crypto');
const { spawn } = require('node:child_process');

const root = __dirname;
const logs = path.join(os.tmpdir(), 'pos-dev-' + crypto.createHash('sha256').update(root).digest('hex').slice(0, 10));
const services = [
  { name: 'backend', port: 8000, cwd: path.join(root, 'backend'), command: 'php', args: ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--no-interaction'] },
  { name: 'frontend', port: 4200, cwd: path.join(root, 'frontend'), command: process.execPath, args: [path.join(root, 'frontend/node_modules/@angular/cli/bin/ng.js'), 'serve', '--host=127.0.0.1', '--port=4200'] },
];

function listening(port) {
  return new Promise(resolve => {
    const socket = net.connect({ host: '127.0.0.1', port });
    const done = result => { socket.destroy(); resolve(result); };
    socket.setTimeout(1500);
    socket.once('connect', () => done(true));
    socket.once('error', () => done(false));
    socket.once('timeout', () => done(false));
  });
}

async function start(service) {
  if (await listening(service.port)) {
    console.log(service.name + ': port ' + service.port + ' is already in use; no duplicate started.');
    return;
  }
  const pidFile = path.join(logs, service.name + '.pid');
  if (fs.existsSync(pidFile)) {
    const pid = Number(fs.readFileSync(pidFile, 'utf8'));
    if (Number.isInteger(pid) && pid > 0) {
      try {
        process.kill(pid, 0);
        console.log(service.name + ': previous launcher PID is still alive (' + pid + '); inspect logs before retrying.');
        return;
      } catch (error) {
        if (error.code !== 'ESRCH') throw error;
      }
    }
  }
  const logPath = path.join(logs, service.name + '.log');
  const output = fs.openSync(logPath, 'a');
  fs.writeSync(output, '\n[' + new Date().toISOString() + '] Starting ' + service.name + '\n');
  const child = spawn(service.command, service.args, {
    cwd: service.cwd, detached: true, windowsHide: true, stdio: ['ignore', output, output],
  });
  await new Promise((resolve, reject) => {
    child.once('spawn', resolve);
    child.once('error', reject);
  }).finally(() => fs.closeSync(output));
  fs.writeFileSync(pidFile, String(child.pid));
  child.unref();
  console.log(service.name + ': starting, PID ' + child.pid + '. Log: ' + logPath);
}

(async () => {
  fs.mkdirSync(logs, { recursive: true });
  for (const service of services) await start(service);
  console.log('Frontend: http://127.0.0.1:4200');
  console.log('Swagger: http://127.0.0.1:8000/docs/api');
  console.log('Angular can take time to compile. Starting processes is not a readiness check.');
})().catch(error => {
  console.error('POS startup failed: ' + error.message);
  process.exitCode = 1;
});
