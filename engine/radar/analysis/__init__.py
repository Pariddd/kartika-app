"""Analisis koordinasi.

Alur: kemiripan teks + ko-aktivitas waktu -> graf akun -> klaster (Louvain)
-> empat sinyal independen per klaster, masing-masing dibandingkan dengan baseline
seluruh dataset -> klaster ditandai bila minimal `min_signals` sinyal aktif.

Graf hanya alat pembentuk klaster, bukan sinyal: sinyal teks dan waktu dihitung
ulang terhadap baseline agar satu bukti tidak dihitung dua kali.
"""
