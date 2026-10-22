const express = require('express');
const faceapi = require('@vladmandic/face-api');

const tf    = require('@tensorflow/tfjs');
const sharp = require('sharp');
const path  = require('path');

const { assessSelfie, mainFace, limits } = require('./quality');

const app       = express();
const PORT      = process.env.PORT || 5001;
const THRESHOLD = Number(process.env.FACE_MATCH_THRESHOLD) || 0.5;
const MODELS    = path.join(__dirname, 'node_modules/@vladmandic/face-api/model');

// Naik setiap kali perilaku /verify berubah. Dibaca `php artisan face:check` untuk
// mendeteksi layanan lama yang belum di-restart setelah deploy.
const VERSION  = 2;
const FEATURES = ['verify', 'analyze', 'brightness', 'multiple_faces', 'face_size'];

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

/** Foto -> piksel RGB (maks 640px). null bila foto tidak bisa dibaca. */
async function toRgb(base64) {
  try {
    const { data, info } = await sharp(Buffer.from(base64, 'base64'))
      .resize(640, 640, { fit: 'inside', withoutEnlargement: true })
      .removeAlpha()
      .raw()
      .toBuffer({ resolveWithObject: true });

    return { data, width: info.width, height: info.height };
  } catch {
    return null;
  }
}

/** Semua wajah di foto beserta kotak, skor, dan descriptor-nya. null bila foto tidak terbaca. */
async function inspect(base64) {
  const rgb = await toRgb(base64);
  if (!rgb) return null;

  const tensor = tf.tensor3d(new Uint8Array(rgb.data), [rgb.height, rgb.width, 3]);
  try {
    const results = await faceapi
      .detectAllFaces(tensor, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptors();

    const faces = results.map((r) => ({
      score: r.detection.score,
      box: {
        x: r.detection.box.x,
        y: r.detection.box.y,
        width: r.detection.box.width,
        height: r.detection.box.height,
      },
      descriptor: r.descriptor,
    }));

    return { rgb, faces };
  } finally {
    tensor.dispose();
  }
}

const reject = (reason, message, quality = null) => ({
  match: false,
  similarity: 0,
  distance: 1,
  reason,
  message,
  quality,
});

// ── Health check ─────────────────────────────────────────────────────────────
app.get('/health', (_req, res) => {
  res.json({
    status:        'ok',
    models_loaded: modelsLoaded,
    version:       VERSION,
    features:      FEATURES,
    threshold:     THRESHOLD,
    limits:        limits(),
  });
});

// ── Analyze: cek kualitas satu foto (dipakai saat mendaftarkan foto wajah) ───
app.post('/analyze', async (req, res) => {
  if (!modelsLoaded) {
    return res.status(503).json({ ok: false, message: 'Service belum siap, coba lagi.' });
  }

  const { photo_base64 } = req.body;
  if (!photo_base64) {
    return res.status(400).json({ error: 'photo_base64 wajib diisi.' });
  }

  try {
    const photo = await inspect(photo_base64);
    if (!photo) {
      return res.json({ ok: false, reason: 'invalid_image', message: 'Foto tidak dapat dibaca. Ambil foto ulang.', quality: null });
    }

    const result = assessSelfie(photo.faces, photo.rgb);
    console.log(`[face-service] analyze ok=${result.ok} reason=${result.reason} quality=${JSON.stringify(result.quality)}`);

    return res.json(result);
  } catch (err) {
    console.error('[face-service] Error:', err.message);
    return res.status(500).json({ ok: false, message: 'Terjadi kesalahan internal.' });
  }
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
    const selfie = await inspect(selfie_base64);
    if (!selfie) {
      return res.json(reject('invalid_image', 'Foto tidak dapat dibaca. Ambil foto ulang.'));
    }

    // Kualitas selfie diperiksa lebih dulu: foto gelap/jauh/ganda tidak dibandingkan sama sekali.
    const assessment = assessSelfie(selfie.faces, selfie.rgb);
    if (!assessment.ok) {
      console.log(`[face-service] ditolak reason=${assessment.reason} quality=${JSON.stringify(assessment.quality)}`);
      return res.json(reject(assessment.reason, assessment.message, assessment.quality));
    }

    const reference = await inspect(reference_base64);
    if (!reference || reference.faces.length === 0) {
      return res.json(
        reject(
          'reference_invalid',
          'Foto wajah yang terdaftar tidak bisa dipakai. Daftarkan ulang di menu Akun, lalu Verifikasi Wajah.',
          assessment.quality,
        ),
      );
    }

    // Foto lama yang kualitasnya buruk tetap dibandingkan (agar tidak mengunci semua orang
    // sekaligus), tetapi dilaporkan supaya bisa diminta mendaftar ulang.
    const referenceCheck = assessSelfie(reference.faces, reference.rgb);

    const distance   = faceapi.euclideanDistance(
      mainFace(selfie.faces).descriptor,
      mainFace(reference.faces).descriptor,
    );
    const similarity = Math.round(Math.max(0, 1 - distance) * 10000) / 10000;
    const match      = distance < THRESHOLD;

    console.log(
      `[face-service] distance=${distance.toFixed(4)} similarity=${similarity} match=${match} ` +
      `quality=${JSON.stringify(assessment.quality)} reference=${referenceCheck.reason}`,
    );

    return res.json({
      match,
      similarity,
      distance:   Math.round(distance * 10000) / 10000,
      reason:     match ? 'ok' : 'mismatch',
      message:    match
        ? 'Verifikasi wajah berhasil.'
        : 'Verifikasi wajah gagal. Wajah tidak sesuai dengan foto yang terdaftar.',
      quality:    assessment.quality,
      reference_quality: referenceCheck.ok
        ? null
        : { reason: referenceCheck.reason, ...referenceCheck.quality },
    });

  } catch (err) {
    console.error('[face-service] Error:', err.message);
    return res.status(500).json({ match: false, message: 'Terjadi kesalahan internal.' });
  }
});

// ── Start ─────────────────────────────────────────────────────────────────────
loadModels().then(() => {
  app.listen(PORT, '127.0.0.1', () => {
    console.log(`[face-service] Running on http://127.0.0.1:${PORT} (v${VERSION})`);
  });
}).catch(err => {
  console.error('[face-service] Gagal load models:', err);
  process.exit(1);
});
