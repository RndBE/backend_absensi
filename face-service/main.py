import base64
import io
import logging

import face_recognition
import numpy as np
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from PIL import Image

app = FastAPI(title="Face Verification Service")
logger = logging.getLogger("uvicorn")


class VerifyRequest(BaseModel):
    selfie_base64: str
    reference_base64: str


def decode_image(b64: str) -> np.ndarray:
    try:
        data = base64.b64decode(b64)
        img = Image.open(io.BytesIO(data)).convert("RGB")
        return np.array(img)
    except Exception as e:
        raise ValueError(f"Gagal decode gambar: {e}")


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/verify")
def verify_face(req: VerifyRequest):
    try:
        selfie_img    = decode_image(req.selfie_base64)
        reference_img = decode_image(req.reference_base64)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    selfie_encodings    = face_recognition.face_encodings(selfie_img)
    reference_encodings = face_recognition.face_encodings(reference_img)

    if not selfie_encodings:
        return {
            "match":      False,
            "similarity": 0.0,
            "distance":   1.0,
            "message":    "Wajah tidak terdeteksi pada foto selfie. Pastikan wajah terlihat jelas.",
        }

    if not reference_encodings:
        return {
            "match":      False,
            "similarity": 0.0,
            "distance":   1.0,
            "message":    "Wajah tidak terdeteksi pada foto referensi yang terdaftar.",
        }

    selfie_enc    = selfie_encodings[0]
    reference_enc = reference_encodings[0]

    # face_recognition distance: 0.0 = identik, >=0.6 = orang berbeda
    distance   = float(face_recognition.face_distance([reference_enc], selfie_enc)[0])
    similarity = round(max(0.0, 1.0 - distance), 4)

    # Threshold 0.5 → ~80% similarity → setara akurasi tinggi
    match = distance < 0.5

    logger.info(f"Face verify — distance={distance:.4f} similarity={similarity:.4f} match={match}")

    return {
        "match":      match,
        "similarity": similarity,
        "distance":   round(distance, 4),
        "message":    "Verifikasi wajah berhasil." if match else "Verifikasi wajah gagal. Wajah tidak sesuai.",
    }
