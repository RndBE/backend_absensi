const express = require('express');
const faceapi = require('@vladmandic/face-api');

const tf    = require('@tensorflow/tfjs');
const sharp = require('sharp');
const path  = require('path');

const app       = express();
const PORT      = process.env.PORT || 5001;
const THRESHOLD = 0.5;
const MODELS    = path.join(__dirname, 'node_modules/@vladmandic/face-api/model');

app.use(express.json({ limit: '20mb' }));

let modelsLoaded = false;

async function loadModels() {
  // Pakai WASM backend (5-10x lebih cepat dari pure JS)
  try {
    require('@tensorflow/tfjs-backend-wasm');
    await tf.setBackend('wasm');
  } catch {
    await tf.setBackend('cpu');
  }
  await tf.ready();
  console.log(`[face-service] TF backend: ${tf.getBackend()}`);

  await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODELS);
  await faceapi.nets.faceLandmark68Net.loadFromDisk(MODELS);
  await faceapi.nets.faceRecognitionNet.loadFromDisk(MODELS);
  modelsLoaded = true;
  console.log('[face-service] Models loaded.');
}

async function toTensor(base64) {
  const buffer = Buffer.from(base64, 'base64');
  const { data, info } = await sharp(buffer)
    .resize(640, 640, { fit: 'inside', withoutEnlargement: true })
    .removeAlpha()
    .raw()
    .toBuffer({ resolveWithObject: true });

  return tf.tensor3d(new Uint8Array(data), [info.height, info.width, 3]);
}

async function getDescriptor(base64) {
  const tensor = await toTensor(base64);
  try {
    const result = await faceapi
      .detectSingleFace(tensor, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor();
    return result ?? null;
  } finally {
    tensor.dispose();
  }
}

// ── Health check ─────────────────────────────────────────────────────────────
app.get('/health', (_req, res) => {
  res.json({ status: 'ok', models_loaded: modelsLoaded });
});

// ── Verify endpoint ──────────────────────────────────────────────────────────
app.post('/verify', async (req, res) => {
  if (!modelsLoaded) {
    return res.status(503).json({ match: false, message: 'Service belum siap, coba lagi.' });
  }

  const { selfie_base64, reference_base64 } = req.body;

  if (!selfie_base64 || !reference_base64) {
    return res.status(400).json({ error: 'selfie_base64 dan reference_base64 wajib diisi.' });
  }

  try {
    const [selfie, reference] = await Promise.all([
      getDescriptor(selfie_base64),
      getDescriptor(reference_base64),
    ]);

    if (!selfie) {
      return res.json({
        match:      false,
        similarity: 0,
        distance:   1,
        message:    'Wajah tidak terdeteksi pada foto selfie. Pastikan wajah terlihat jelas dan pencahayaan cukup.',
      });
    }

    if (!reference) {
      return res.json({
        match:      false,
        similarity: 0,
        distance:   1,
        message:    'Wajah tidak terdeteksi pada foto referensi yang terdaftar.',
      });
    }

    const distance   = faceapi.euclideanDistance(selfie.descriptor, reference.descriptor);
    const similarity = Math.round(Math.max(0, 1 - distance) * 10000) / 10000;
    const match      = distance < THRESHOLD;

    console.log(`[face-service] distance=${distance.toFixed(4)} similarity=${similarity} match=${match}`);

    return res.json({
      match,
      similarity,
      distance:   Math.round(distance * 10000) / 10000,
      message:    match
        ? 'Verifikasi wajah berhasil.'
        : 'Verifikasi wajah gagal. Wajah tidak sesuai dengan foto yang terdaftar.',
    });

  } catch (err) {
    console.error('[face-service] Error:', err.message);
    return res.status(500).json({ match: false, message: 'Terjadi kesalahan internal.' });
  }
});

// ── Start ─────────────────────────────────────────────────────────────────────
loadModels().then(() => {
  app.listen(PORT, '127.0.0.1', () => {
    console.log(`[face-service] Running on http://127.0.0.1:${PORT}`);
  });
}).catch(err => {
  console.error('[face-service] Gagal load models:', err);
  process.exit(1);
});
