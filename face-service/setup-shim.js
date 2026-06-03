/**
 * Membuat shim @tensorflow/tfjs-node → @tensorflow/tfjs
 * Diperlukan di Windows karena tfjs-node butuh native binary yang
 * tidak bisa di-compile di Windows tanpa Visual Studio C++ lengkap.
 * Di Linux (production), @tensorflow/tfjs-node akan install native binary.
 */
const fs   = require('fs');
const path = require('path');

const shimDir  = path.join(__dirname, 'node_modules', '@tensorflow', 'tfjs-node');
const distDir  = path.join(shimDir, 'dist');
const nodeFile = path.join(shimDir, 'dist', 'index.js');
const pkgFile  = path.join(shimDir, 'package.json');

// Kalau sudah ada native binding, tidak perlu shim
if (fs.existsSync(path.join(shimDir, 'lib', 'napi-v8', 'tfjs_binding.node'))) {
  console.log('[setup-shim] Native tfjs-node ditemukan, shim tidak diperlukan.');
  process.exit(0);
}

fs.mkdirSync(distDir, { recursive: true });
fs.writeFileSync(pkgFile, JSON.stringify({ name: '@tensorflow/tfjs-node', version: '4.22.0', main: 'dist/index.js' }));
fs.writeFileSync(nodeFile, 'module.exports = require("@tensorflow/tfjs");\n');

console.log('[setup-shim] Shim @tensorflow/tfjs-node dibuat (Windows fallback).');
