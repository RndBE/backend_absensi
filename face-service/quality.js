/**
 * Penilaian kualitas selfie. Murni (tanpa TensorFlow) supaya bisa diuji dengan data buatan.
 *
 * Urutan penolakan: wajah tidak ada, wajah lebih dari satu, wajah terlalu kecil/jauh,
 * terlalu gelap, terlalu silau. Pencocokan wajah baru dijalankan kalau semua lolos.
 */

const num = (name, fallback) => {
  const value = Number(process.env[name]);
  return Number.isFinite(value) && process.env[name] !== '' ? value : fallback;
};

// Nilai awal. Bisa disetel lewat environment tanpa mengubah kode:
// FACE_MIN_BRIGHTNESS, FACE_MIN_HIGHLIGHT, FACE_MAX_BRIGHTNESS, FACE_MIN_RATIO.
const limits = () => ({
  // Rata-rata kecerahan (0-255) area wajah di bawah ini dianggap gelap.
  minBrightness: num('FACE_MIN_BRIGHTNESS', 45),
  // Bagian terang wajah (persentil 90). Wajah berkulit gelap tetap punya bagian terang
  // bila cahayanya cukup, sedangkan foto di ruang redup tidak.
  minHighlight: num('FACE_MIN_HIGHLIGHT', 70),
  maxBrightness: num('FACE_MAX_BRIGHTNESS', 215),
  // Lebar wajah minimal terhadap lebar foto.
  minFaceRatio: num('FACE_MIN_RATIO', 0.2),
  // Wajah kedua dihitung bila luasnya minimal sebesar ini dari wajah utama
  // (orang jauh di belakang tidak ikut dihitung).
  secondFaceArea: 0.35,
});

const MESSAGES = {
  no_face:
    'Wajah tidak terdeteksi pada foto selfie. Pastikan wajah terlihat jelas dan pencahayaan cukup.',
  multiple_faces:
    'Terdeteksi lebih dari satu wajah. Pastikan hanya Anda yang ada di dalam foto.',
  face_small: 'Wajah terlalu jauh dari kamera. Dekatkan wajah ke dalam bingkai.',
  too_dark:
    'Pencahayaan kurang terang. Pindah ke tempat yang lebih terang lalu coba lagi.',
  too_bright:
    'Foto terlalu silau. Hindari cahaya yang langsung menyorot ke kamera.',
};

const area = (box) => box.width * box.height;

/** Wajah dengan luas terbesar dianggap wajah utama. */
function mainFace(faces) {
  return faces.reduce((best, face) => (best === null || area(face.box) > area(best.box) ? face : best), null);
}

/**
 * Rata-rata dan persentil 90 kecerahan (0-255) di dalam kotak wajah.
 * `rgb` = { data: Uint8Array RGB berurutan, width, height }.
 */
function faceBrightness(rgb, box) {
  const x0 = Math.max(0, Math.floor(box.x));
  const y0 = Math.max(0, Math.floor(box.y));
  const x1 = Math.min(rgb.width, Math.ceil(box.x + box.width));
  const y1 = Math.min(rgb.height, Math.ceil(box.y + box.height));

  const histogram = new Array(256).fill(0);
  let sum = 0;
  let count = 0;

  for (let y = y0; y < y1; y += 1) {
    for (let x = x0; x < x1; x += 1) {
      const i = (y * rgb.width + x) * 3;
      const luma = Math.round(0.299 * rgb.data[i] + 0.587 * rgb.data[i + 1] + 0.114 * rgb.data[i + 2]);
      histogram[luma] += 1;
      sum += luma;
      count += 1;
    }
  }

  if (count === 0) return { mean: 0, highlight: 0 };

  const target = count * 0.9;
  let seen = 0;
  let highlight = 255;
  for (let level = 0; level < 256; level += 1) {
    seen += histogram[level];
    if (seen >= target) {
      highlight = level;
      break;
    }
  }

  return { mean: sum / count, highlight };
}

/**
 * @param faces `[{ score, box: { x, y, width, height } }]` dalam piksel foto.
 * @param rgb   `{ data, width, height }`
 * @returns `{ ok, reason, message, quality }`
 */
function assessSelfie(faces, rgb) {
  const max = limits();
  const quality = { faces: faces.length, face_ratio: null, brightness: null, highlight: null };

  const fail = (reason) => ({ ok: false, reason, message: MESSAGES[reason], quality });

  if (faces.length === 0) {
    // Di ruangan sangat gelap detektor tidak menemukan wajah apa pun. Pesannya harus tetap
    // "kurang terang", bukan "wajah tidak terdeteksi", agar orang tahu apa yang harus dibenahi.
    const center = faceBrightness(rgb, {
      x: rgb.width * 0.25,
      y: rgb.height * 0.2,
      width: rgb.width * 0.5,
      height: rgb.height * 0.6,
    });
    quality.brightness = Math.round(center.mean * 10) / 10;
    quality.highlight = center.highlight;

    return fail(center.mean < max.minBrightness || center.highlight < max.minHighlight ? 'too_dark' : 'no_face');
  }

  const main = mainFace(faces);
  quality.face_ratio = Math.round((main.box.width / rgb.width) * 1000) / 1000;

  const others = faces.filter((face) => face !== main);
  if (others.some((face) => area(face.box) >= area(main.box) * max.secondFaceArea)) {
    return fail('multiple_faces');
  }

  if (quality.face_ratio < max.minFaceRatio) return fail('face_small');

  const { mean, highlight } = faceBrightness(rgb, main.box);
  quality.brightness = Math.round(mean * 10) / 10;
  quality.highlight = highlight;

  if (mean < max.minBrightness || highlight < max.minHighlight) return fail('too_dark');
  if (mean > max.maxBrightness) return fail('too_bright');

  return { ok: true, reason: 'ok', message: 'Foto selfie memenuhi syarat.', quality };
}

module.exports = { assessSelfie, faceBrightness, mainFace, limits, MESSAGES };
