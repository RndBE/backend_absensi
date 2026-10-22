// Jalankan: npm test  (memakai node:test bawaan Node, tanpa dependensi tambahan)
const test = require('node:test');
const assert = require('node:assert/strict');

const { assessSelfie, faceBrightness, MESSAGES } = require('./quality');

/** Foto abu-abu seragam; `fill(x, y)` bisa mengubah kecerahan per piksel. */
function photo(width, height, fill) {
  const data = new Uint8Array(width * height * 3);
  for (let y = 0; y < height; y += 1) {
    for (let x = 0; x < width; x += 1) {
      const value = typeof fill === 'function' ? fill(x, y) : fill;
      const i = (y * width + x) * 3;
      data[i] = value;
      data[i + 1] = value;
      data[i + 2] = value;
    }
  }
  return { data, width, height };
}

const face = (x, y, width, height = width, score = 0.95) => ({ score, box: { x, y, width, height } });

test('foto terang dengan satu wajah normal lolos', () => {
  const result = assessSelfie([face(200, 120, 240)], photo(640, 480, 150));

  assert.equal(result.ok, true);
  assert.equal(result.reason, 'ok');
  assert.equal(result.quality.faces, 1);
  assert.equal(result.quality.brightness, 150);
});

test('tanpa wajah ditolak', () => {
  const result = assessSelfie([], photo(640, 480, 150));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'no_face');
  assert.equal(result.message, MESSAGES.no_face);
});

test('tanpa wajah di ruangan sangat gelap dilaporkan sebagai kurang terang', () => {
  const result = assessSelfie([], photo(640, 480, 12));

  assert.equal(result.reason, 'too_dark');
  assert.equal(result.message, MESSAGES.too_dark);
});

test('tanpa wajah, hanya bagian tengah yang dinilai (tepi gelap tidak dihitung)', () => {
  const rgb = photo(640, 480, (x, y) => (x >= 160 && x < 480 && y >= 96 && y < 384 ? 150 : 10));

  assert.equal(assessSelfie([], rgb).reason, 'no_face');
});

test('foto gelap ditolak walau wajah terdeteksi', () => {
  const result = assessSelfie([face(200, 120, 240)], photo(640, 480, 25));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'too_dark');
  assert.match(result.message, /Pencahayaan kurang terang/);
});

test('remang-remang (rata-rata lewat batas tapi tanpa bagian terang) ditolak', () => {
  // Rata-rata 60, bagian terang tertinggi 64: ruang redup, bukan kulit gelap yang tersinari baik.
  const result = assessSelfie([face(200, 120, 240)], photo(640, 480, 60));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'too_dark');
});

test('wajah berkulit gelap yang tersinari baik tidak dianggap gelap', () => {
  // Kebanyakan piksel gelap (~55) tetapi ada sorotan terang (sekitar 20% piksel 140).
  const result = assessSelfie(
    [face(200, 120, 240)],
    photo(640, 480, (x) => (x % 5 === 0 ? 140 : 55)),
  );

  assert.equal(result.ok, true);
});

test('latar terang tetapi wajahnya gelap (membelakangi jendela) ditolak', () => {
  const rgb = photo(640, 480, (x, y) => (x >= 200 && x < 440 && y >= 120 && y < 360 ? 30 : 230));

  const result = assessSelfie([face(200, 120, 240)], rgb);

  assert.equal(result.reason, 'too_dark');
});

test('latar gelap tetapi wajahnya terang tetap lolos', () => {
  const rgb = photo(640, 480, (x, y) => (x >= 200 && x < 440 && y >= 120 && y < 360 ? 150 : 20));

  const result = assessSelfie([face(200, 120, 240)], rgb);

  assert.equal(result.ok, true);
});

test('foto terlalu silau ditolak', () => {
  const result = assessSelfie([face(200, 120, 240)], photo(640, 480, 245));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'too_bright');
});

test('dua wajah berukuran sebanding ditolak', () => {
  const result = assessSelfie([face(60, 120, 240), face(360, 120, 220)], photo(640, 480, 150));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'multiple_faces');
});

test('orang kecil jauh di belakang tidak ikut dihitung', () => {
  const result = assessSelfie([face(200, 120, 240), face(560, 40, 50)], photo(640, 480, 150));

  assert.equal(result.ok, true);
  assert.equal(result.quality.faces, 2);
});

test('wajah terlalu kecil (jauh dari kamera) ditolak', () => {
  const result = assessSelfie([face(280, 200, 80)], photo(640, 480, 150));

  assert.equal(result.ok, false);
  assert.equal(result.reason, 'face_small');
});

test('wajah tepat di batas ukuran lolos, di bawah batas ditolak', () => {
  assert.equal(assessSelfie([face(250, 150, 128)], photo(640, 480, 150)).ok, true);
  assert.equal(assessSelfie([face(250, 150, 120)], photo(640, 480, 150)).reason, 'face_small');
});

test('kotak wajah yang keluar dari foto dipotong, tidak error', () => {
  const rgb = photo(640, 480, 150);

  const stats = faceBrightness(rgb, { x: -50, y: -50, width: 300, height: 300 });

  assert.equal(Math.round(stats.mean), 150);
});

test('kotak wajah di luar foto sama sekali dianggap gelap', () => {
  const result = assessSelfie([face(700, 600, 200)], photo(640, 480, 150));

  assert.equal(result.ok, false);
});

test('urutan penolakan: wajah ganda didahulukan daripada gelap', () => {
  const result = assessSelfie([face(60, 120, 240), face(360, 120, 240)], photo(640, 480, 10));

  assert.equal(result.reason, 'multiple_faces');
});

test('batas cahaya bisa disetel lewat environment', () => {
  process.env.FACE_MIN_BRIGHTNESS = '200';
  process.env.FACE_MIN_HIGHLIGHT = '200';
  try {
    const result = assessSelfie([face(200, 120, 240)], photo(640, 480, 150));
    assert.equal(result.reason, 'too_dark');
  } finally {
    delete process.env.FACE_MIN_BRIGHTNESS;
    delete process.env.FACE_MIN_HIGHLIGHT;
  }
});
