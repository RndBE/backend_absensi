"""
Development stub untuk testing lokal di Windows.
Tidak butuh dlib/face_recognition.
Pakai PIL color histogram — sama seperti logika PHP lama, hanya untuk
memverifikasi bahwa flow Laravel → Python → response benar.

Untuk production (Linux server), gunakan main.py yang pakai face_recognition.
"""

import base64
import io
import math

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from PIL import Image

app = FastAPI(title="Face Verification Service [DEV]")


class VerifyRequest(BaseModel):
    selfie_base64: str
    reference_base64: str


def decode_image(b64: str) -> Image.Image:
    try:
        data = base64.b64decode(b64)
        return Image.open(io.BytesIO(data)).convert("RGB")
    except Exception as e:
        raise ValueError(f"Gagal decode gambar: {e}")


def color_histogram(img: Image.Image, bins: int = 16) -> list[float]:
    img = img.resize((64, 64))
    hist = [0.0] * (bins * 3)
    pixels = list(img.getdata())
    for r, g, b in pixels:
        hist[r * bins // 256] += 1
        hist[bins + g * bins // 256] += 1
        hist[bins * 2 + b * bins // 256] += 1
    return hist


def cosine_similarity(h1: list[float], h2: list[float]) -> float:
    dot  = sum(a * b for a, b in zip(h1, h2))
    mag1 = math.sqrt(sum(a * a for a in h1))
    mag2 = math.sqrt(sum(b * b for b in h2))
    if mag1 == 0 or mag2 == 0:
        return 0.0
    return dot / (mag1 * mag2)


@app.get("/health")
def health():
    return {"status": "ok", "mode": "dev (color histogram — bukan face recognition)"}


@app.post("/verify")
def verify_face(req: VerifyRequest):
    try:
        selfie_img    = decode_image(req.selfie_base64)
        reference_img = decode_image(req.reference_base64)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    h1 = color_histogram(selfie_img)
    h2 = color_histogram(reference_img)
    similarity = round(cosine_similarity(h1, h2), 4)
    match      = similarity >= 0.50

    return {
        "match":      match,
        "similarity": similarity,
        "distance":   round(1.0 - similarity, 4),
        "message":    "Verifikasi berhasil [DEV]." if match else "Verifikasi gagal [DEV].",
        "mode":       "dev",
    }
