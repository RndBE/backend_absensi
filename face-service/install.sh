#!/bin/bash
# Setup script untuk face-service di server Linux (Plesk VPS)
# Jalankan sekali dari direktori face-service: bash install.sh

set -e

echo "=== Install system dependencies ==="
apt-get update
apt-get install -y python3 python3-pip python3-venv cmake build-essential libopenblas-dev

echo "=== Buat virtual environment ==="
python3 -m venv venv
source venv/bin/activate

echo "=== Install Python packages ==="
pip install --upgrade pip
pip install -r requirements.txt

echo "=== Copy systemd service ==="
cp face-service.service /etc/systemd/system/face-service.service

echo ""
echo "=== SELESAI ==="
echo "Langkah berikutnya:"
echo "  1. Edit /etc/systemd/system/face-service.service — sesuaikan User dan WorkingDirectory"
echo "  2. systemctl daemon-reload"
echo "  3. systemctl enable face-service"
echo "  4. systemctl start face-service"
echo "  5. systemctl status face-service"
echo "  6. Cek: curl http://127.0.0.1:5001/health"
