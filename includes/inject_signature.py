#!/usr/bin/env python3
"""
inject_signature.py — Menyematkan tanda tangan visual ke PDF
Dipanggil dari PHP via shell_exec()

Argumen:
  1. input_pdf   — path file PDF sumber
  2. output_pdf  — path file PDF hasil (bertanda tangan)
  3. page_num    — halaman ke berapa (1-based)
  4. x           — posisi X overlay (pixel di canvas PDF.js, 96dpi)
  5. y           — posisi Y overlay (pixel di canvas PDF.js)
  6. w           — lebar overlay (pixel)
  7. h           — tinggi overlay (pixel)
  8. signer_name — nama penandatangan
  9. signed_at   — timestamp penandatanganan
 10. sig_image   — (opsional) path gambar tanda tangan PNG/JPG
"""

import sys
import io
import os
from datetime import datetime

def main():
    args = sys.argv[1:]
    if len(args) < 9:
        print("ERROR: insufficient arguments", file=sys.stderr)
        print(f"Got {len(args)}: {args}", file=sys.stderr)
        sys.exit(1)

    input_pdf   = args[0]
    output_pdf  = args[1]
    page_num    = max(1, int(args[2]))
    x_px        = float(args[3])
    y_px        = float(args[4])
    w_px        = float(args[5])
    h_px        = float(args[6])
    signer_name = args[7]
    signed_at   = args[8]
    sig_image   = args[9] if len(args) > 9 and args[9] and os.path.exists(args[9]) else None

    from pypdf import PdfReader, PdfWriter
    from reportlab.pdfgen import canvas as rl_canvas
    from reportlab.lib.colors import HexColor, white, black, Color
    from reportlab.lib.utils import ImageReader

    # Baca PDF asli
    reader = PdfReader(input_pdf)
    total_pages = len(reader.pages)
    page_idx = min(page_num - 1, total_pages - 1)
    page = reader.pages[page_idx]

    # Ukuran halaman PDF dalam point (1pt = 1/72 inch)
    pdf_w_pt = float(page.mediabox.width)
    pdf_h_pt = float(page.mediabox.height)

    # PDF.js render dengan scale=1.3 dan asumsi 96dpi layar
    scale_factor = (96.0 / 72.0) * 1.3   # px per pt
    canvas_w_px  = pdf_w_pt * scale_factor
    canvas_h_px  = pdf_h_pt * scale_factor

    # Konversi dari koordinat canvas (px, origin top-left) ke PDF (pt, origin bottom-left)
    ratio_x = pdf_w_pt / canvas_w_px
    ratio_y = pdf_h_pt / canvas_h_px

    sig_x_pt = x_px * ratio_x
    sig_w_pt = w_px * ratio_x
    sig_h_pt = h_px * ratio_y
    # Y: canvas origin top, PDF origin bottom — flip
    sig_y_pt = pdf_h_pt - (y_px * ratio_y) - sig_h_pt

    # Buat overlay signature dengan ReportLab
    packet = io.BytesIO()
    c = rl_canvas.Canvas(packet, pagesize=(pdf_w_pt, pdf_h_pt))

    # ================================================================
    # TAMPILAN TTD: mirip "DIGITALLY SIGNED BY UNILA"
    # Layout:
    #   [ gambar TTD ]  |  DIGITALLY SIGNED BY UNILA
    #                   |  Nama: Rif'an Habibi
    #                   |  Waktu: 28/5/2026 WIB
    # ================================================================

    padding = 3
    divider_ratio = 0.45   # 45% lebar untuk area gambar TTD, 55% untuk teks

    left_w  = sig_w_pt * divider_ratio
    right_x = sig_x_pt + left_w
    right_w = sig_w_pt - left_w

    # --- Area latar belakang PUTIH (tanpa border/outline) ---
    c.setFillColor(HexColor('#FFFFFF'))
    c.setStrokeColor(HexColor('#FFFFFF'))
    c.setLineWidth(0)
    c.rect(sig_x_pt, sig_y_pt, sig_w_pt, sig_h_pt, fill=1, stroke=0)

    # --- Gambar tanda tangan di sisi kiri ---
    if sig_image:
        try:
            img = ImageReader(sig_image)
            img_pad = padding
            img_x = sig_x_pt + img_pad
            img_y = sig_y_pt + img_pad
            img_w = left_w - img_pad * 2
            img_h = sig_h_pt - img_pad * 2
            c.drawImage(img, img_x, img_y, width=img_w, height=img_h,
                        mask='auto', preserveAspectRatio=True, anchor='c')
        except Exception:
            pass

    # --- Garis vertikal pemisah ---
    c.setStrokeColor(HexColor('#CCCCCC'))
    c.setLineWidth(0.8)
    c.line(right_x - 2, sig_y_pt + padding, right_x - 2, sig_y_pt + sig_h_pt - padding)

    # --- Teks di sisi kanan ---
    text_x = right_x + padding

    # Ukuran font proporsional terhadap tinggi kotak
    font_label = max(4.5, min(7.0,  sig_h_pt * 0.14))   # "DIGITALLY SIGNED BY ..."
    font_name  = max(5.0, min(8.5,  sig_h_pt * 0.18))   # "Nama: ..."
    font_time  = max(4.0, min(6.5,  sig_h_pt * 0.13))   # "Waktu: ..."

    line_gap = sig_h_pt * 0.22   # jarak antar baris

    # Posisi baris dari atas ke bawah
    # Baris 1 — label biru (paling atas)
    y_label = sig_y_pt + sig_h_pt - padding - font_label
    # Baris 2 — nama (tengah atas)
    y_name  = y_label - line_gap
    # Baris 3 — waktu (tengah bawah)
    y_time  = y_name - line_gap * 0.9

    # "DIGITALLY SIGNED BY UNILA" — biru, bold kecil
    c.setFont('Helvetica-Bold', font_label)
    c.setFillColor(HexColor('#1a4fd6'))
    label_text = 'DIGITALLY SIGNED BY UNILA'
    c.drawString(text_x, y_label, label_text)

    # "Nama: ..." — hitam, bold
    c.setFont('Helvetica-Bold', font_name)
    c.setFillColor(HexColor('#0d1117'))
    display_name = signer_name[:35]
    c.drawString(text_x, y_name, f"Nama: {display_name}")

    # Format tanggal: "28/5/2026 WIB"
    try:
        # signed_at bisa berupa "2026-05-28 10:30:00" atau sejenisnya
        dt = datetime.strptime(signed_at[:19], '%Y-%m-%d %H:%M:%S')
        waktu_str = f"{dt.day}/{dt.month}/{dt.year} WIB"
    except Exception:
        waktu_str = signed_at[:16] if signed_at else datetime.now().strftime('%d/%m/%Y')

    # "Waktu: ..." — abu-abu
    c.setFont('Helvetica', font_time)
    c.setFillColor(HexColor('#6B7280'))
    c.drawString(text_x, y_time, f"Waktu: {waktu_str}")

    c.save()
    packet.seek(0)

    # Gabungkan overlay dengan halaman PDF asli
    from pypdf import PdfReader as PR2
    overlay_reader = PR2(packet)
    overlay_page = overlay_reader.pages[0]

    # Merge: halaman asli di bawah, overlay di atas
    page.merge_page(overlay_page)

    # Tulis ulang semua halaman
    writer = PdfWriter()
    for i, p in enumerate(reader.pages):
        writer.add_page(p)

    # Metadata
    writer.add_metadata({
        '/Title': f'Dokumen Bertanda Tangan - {signer_name}',
        '/Author': signer_name,
        '/Subject': 'Dokumen ditandatangani secara digital menggunakan SignKu',
        '/Creator': 'SignKu Digital Signature System',
        '/Keywords': f'tanda tangan digital, OTP terverifikasi, {signed_at}',
    })

    with open(output_pdf, 'wb') as f:
        writer.write(f)

    print("SUCCESS")
    sys.exit(0)

if __name__ == '__main__':
    main()
