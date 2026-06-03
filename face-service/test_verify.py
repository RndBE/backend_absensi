"""
Test script untuk verifikasi endpoint /verify berjalan dengan benar.
Jalankan: python test_verify.py
"""

import base64
import io
import json
import urllib.request
from PIL import Image, ImageDraw

BASE_URL = "http://127.0.0.1:5001"


def make_face_image(skin_color=(210, 170, 120), bg_color=(200, 220, 255)) -> str:
    """Buat gambar wajah sederhana (kotak warna) untuk testing."""
    img = Image.new("RGB", (200, 200), bg_color)
    draw = ImageDraw.Draw(img)
    # Oval wajah
    draw.ellipse([50, 30, 150, 170], fill=skin_color)
    # Mata
    draw.ellipse([75, 75, 90, 90],   fill=(50, 30, 20))
    draw.ellipse([110, 75, 125, 90], fill=(50, 30, 20))
    # Mulut
    draw.arc([80, 110, 120, 140], start=0, end=180, fill=(150, 50, 50), width=3)

    buf = io.BytesIO()
    img.save(buf, format="JPEG")
    return base64.b64encode(buf.getvalue()).decode()


def post(endpoint: str, payload: dict) -> dict:
    data = json.dumps(payload).encode()
    req = urllib.request.Request(
        f"{BASE_URL}{endpoint}",
        data=data,
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=10) as resp:
        return json.loads(resp.read())


def test_health():
    with urllib.request.urlopen(f"{BASE_URL}/health", timeout=5) as resp:
        result = json.loads(resp.read())
    assert result["status"] == "ok", "Health check gagal"
    print(f"[PASS] /health : {result}")


def test_same_face():
    img = make_face_image(skin_color=(200, 160, 100))
    result = post("/verify", {"selfie_base64": img, "reference_base64": img})
    assert result["match"] is True, f"Foto sama seharusnya match: {result}"
    print(f"[PASS] foto sama    : similarity={result['similarity']} match={result['match']}")


def test_different_face():
    img1 = make_face_image(skin_color=(200, 160, 100), bg_color=(200, 220, 255))
    img2 = make_face_image(skin_color=(80, 50, 30),    bg_color=(50, 50, 50))
    result = post("/verify", {"selfie_base64": img1, "reference_base64": img2})
    print(f"[INFO] foto berbeda : similarity={result['similarity']} match={result['match']}")


def test_invalid_base64():
    try:
        post("/verify", {"selfie_base64": "bukan-base64!!!", "reference_base64": "juga-salah"})
        print("[FAIL] seharusnya error 400")
    except urllib.error.HTTPError as e:
        assert e.code == 400
        print(f"[PASS] base64 invalid : HTTP 400")


if __name__ == "__main__":
    print("=== Face Service Test ===\n")
    test_health()
    test_same_face()
    test_different_face()
    test_invalid_base64()
    print("\n=== Semua test selesai ===")
