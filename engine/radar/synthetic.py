"""Generator data sintetis dengan kunci jawaban.

Isu fiktif dan netral ("Pasar Lama Kota Sentosa"). Tidak ada orang, akun, atau
domain sungguhan; semua domain memakai akhiran .example (RFC 2606).

Isi dataset preset "standar":
- Percakapan organik: ratusan akun, gaya tulis beragam, waktu mengikuti pola harian,
  satu lonjakan berita yang wajar (banyak akun aktif bersamaan, tetapi hanya sekali).
- Tiga klaster tanaman (kunci jawaban), masing-masing dengan taktik berbeda:
    Tanaman A: salin-tempel, sangat serentak, akun muda, berbagi domain muda.
    Tanaman B: parafrase sedang, serentak, umur akun beragam, tanpa domain.
    Tanaman C: parafrase tinggi, kurang serentak, akun muda.
- Kelompok pengecoh yang sah: akun pedagang membagikan pengumuman resmi yang sama,
  dua kali, dari domain lama. Mereka BUKAN kunci jawaban; bila sistem menandainya,
  itu salah tanda yang harus dikoreksi analis.

Semua acak memakai random.Random(seed): seed yang sama menghasilkan dataset identik.
"""

from __future__ import annotations

import random
import re
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone

from radar.ingest import RawPost

PLATFORM = "synthetic"
PERIOD_START = datetime(2026, 11, 1, 1, 0, tzinfo=timezone.utc)  # 08.00 WIB
PERIOD_HOURS = 24
WIB = timezone(timedelta(hours=7))

# Bobot aktivitas organik per jam WIB (0-23): sepi dini hari, ramai siang dan malam.
HOURLY_WEIGHT = [1, 0.6, 0.4, 0.3, 0.4, 0.8, 1.5, 2.5, 3, 3, 3, 3.2, 3.5, 3, 2.8, 2.6, 2.8, 3.2, 3.8, 4.2, 4.5, 4, 3, 2]

# ---------------------------------------------------------------- teks

_SLOT = re.compile(r"\{([^{}]*)\}")


def render(template: str, rng: random.Random, paraphrase: float) -> str:
    """Isi slot {a|b|c}. paraphrase=0: selalu opsi pertama (salin-tempel persis);
    makin tinggi, makin sering memilih variasi lain."""

    def choose(match: re.Match[str]) -> str:
        options = match.group(1).split("|")
        if len(options) == 1 or rng.random() >= paraphrase:
            return options[0]
        return rng.choice(options[1:])

    return _SLOT.sub(choose, template)


FILLERS = ["Jujur saja,", "Menurut saya,", "Ya memang", "Sudah jelas,", "Coba pikir,", "Intinya,"]
CLOSERS = [".", ".", ".", ". Setuju?", ". Semoga cepat terlaksana.", ". #PasarSentosa", "! Ayo dukung."]


def paraphrase_sentence(template: str, rng: random.Random, level: float) -> str:
    text = render(template, rng, level)
    if level > 0 and rng.random() < level * 0.5:
        filler = rng.choice(FILLERS)
        text = f"{filler} {text[0].lower()}{text[1:]}"
    if level > 0 and rng.random() < level * 0.6:
        text = text.rstrip(".!") + rng.choice(CLOSERS)
    if level > 0 and rng.random() < level * 0.3:
        text = text.lower()
    return text


ORGANIC_OPENERS = [
    "Baru baca soal Pasar Lama.", "Pasar Lama mau ditutup ya?", "Kabar soal Pasar Lama ramai lagi.",
    "Kemarin lewat Pasar Lama,", "Ibu saya jualan di Pasar Lama.", "Jadi ingat masa kecil di Pasar Lama.",
    "Soal relokasi Pasar Lama,", "Ada yang tahu jadwal relokasi pasar?", "Warga Sentosa lagi bahas pasar.",
    "Pasar Lama itu bersejarah.", "Saya sih netral soal pasar.", "Akhirnya ada kejelasan soal pasar?",
]
ORGANIC_MIDDLES = [
    "parkirnya memang semrawut", "tapi pedagang kecil gimana nasibnya", "atapnya sudah bocor di mana-mana",
    "harga sewa kios baru katanya mahal", "lokasi baru agak jauh dari terminal", "drainasenya parah kalau hujan",
    "saya lebih suka direnovasi saja", "relokasi boleh asal pedagang dilibatkan", "banyak kenangan di sana",
    "sebaiknya ada uji publik dulu", "akses angkot ke lokasi baru belum jelas", "kebersihannya memang perlu dibenahi",
    "yang penting jangan sampai pedagang rugi", "pemerintah harus transparan soal anggarannya",
]
ORGANIC_ENDINGS = [
    ".", ". Semoga ada jalan tengah.", ". Kita lihat saja nanti.", "?", ". Gimana pendapat kalian?",
    ". Kasihan pedagang lama.", ". Yang penting rapi.", " sih.", "...", ". Tolong didengar suara warga.",
]
ORGANIC_SHORT = [
    "Setuju sama komentar di atas.", "Semoga cepat beres.", "Nah ini yang penting.", "Betul banget.",
    "Kurang setuju sih.", "Pantau terus.", "Info lokasi barunya di mana ya?", "Mantap kalau jadi rapi.",
]
NEWS_DOMAINS = [
    "harian-sentosa.example", "kabar-kota.example", "warta-daerah.example",
    "suara-warga.example", "portal-berita.example", "infosentosa.example",
]

TALKING_POINTS = {
    "A": [
        "{Pasar Lama|pasar lama|Pasar Lama Sentosa} sudah {tidak layak|tak layak lagi|kumuh dan tidak layak}, {saatnya|sudah waktunya|harus segera} dipindah demi {warga|masyarakat} Sentosa.",
        "{Relokasi pasar|Pemindahan pasar|Relokasi Pasar Lama} adalah {langkah paling masuk akal|keputusan paling tepat|solusi terbaik} untuk kota yang lebih {tertata|rapi|modern}.",
        "{Jangan percaya|Abaikan saja|Jangan termakan} isu {pedagang dirugikan|pedagang ditelantarkan|pasar baru mahal}, {semua sudah diatur|pemerintah sudah siapkan solusinya|faktanya tidak begitu}.",
    ],
    "B": [
        "{Pasar baru|Lokasi baru|Gedung pasar baru} {jauh lebih bersih|lebih higienis|jauh lebih nyaman} dan {aman|tertata|modern}, {warga pasti senang|semua diuntungkan|pembeli pasti suka}.",
        "{Yang menolak relokasi|Penolak relokasi|Orang yang protes} {cuma segelintir|hanya sedikit|bukan mayoritas}, {mayoritas warga mendukung|sebagian besar setuju|warga lain mendukung}.",
    ],
    "C": [
        "{Kota modern|Kota yang maju|Kota maju} {butuh pasar yang representatif|perlu pasar yang layak|pantas punya pasar modern}, {Pasar Lama sudah ketinggalan zaman|pasar lama tidak sesuai zaman|bangunan lama sudah usang}.",
        "{Dukung|Ayo dukung|Mari dukung} {penataan kota|program penataan|rencana penataan}, {pasar baru untuk masa depan|demi Sentosa yang lebih baik|untuk generasi berikutnya}.",
    ],
}
DECOY_ANNOUNCEMENT = (
    "PENGUMUMAN: Paguyuban Pedagang Pasar Lama mengadakan rapat terbuka soal relokasi, "
    "Sabtu pukul 09.00 di balai pasar. Semua pedagang diharapkan hadir."
)


# ---------------------------------------------------------------- konfigurasi


@dataclass(frozen=True)
class PlantedSpec:
    label: str
    size: int
    events: int
    paraphrase: float
    spread_seconds: int
    young_accounts: bool
    domain: str | None
    participation: float = 0.85


@dataclass(frozen=True)
class Preset:
    organic_accounts: int
    planted: tuple[PlantedSpec, ...]
    decoy_size: int


PRESETS: dict[str, Preset] = {
    "standar": Preset(
        organic_accounts=900,
        planted=(
            PlantedSpec("Tanaman A", 38, 6, 0.15, 120, True, "berita-sentosa-terkini.example"),
            PlantedSpec("Tanaman B", 24, 5, 0.5, 300, False, None),
            PlantedSpec("Tanaman C", 17, 5, 0.8, 600, True, None),
        ),
        decoy_size=12,
    ),
    "kontrol": Preset(organic_accounts=900, planted=(), decoy_size=12),
    "parafrase": Preset(
        organic_accounts=900,
        planted=(
            PlantedSpec("Tanaman A", 30, 6, 0.9, 300, False, None),
            PlantedSpec("Tanaman B", 20, 5, 1.0, 600, True, None),
        ),
        decoy_size=12,
    ),
}


@dataclass
class SyntheticDataset:
    posts: list[RawPost]
    # label klaster tanaman -> author_id asli anggota; inilah kunci jawaban.
    planted: dict[str, list[str]] = field(default_factory=dict)
    decoys: list[str] = field(default_factory=list)


# ---------------------------------------------------------------- generator


class _Builder:
    def __init__(self, seed: int) -> None:
        self.rng = random.Random(seed)
        self.posts: list[RawPost] = []
        self._account_seq = 0
        self._post_seq = 0
        self.profiles: dict[str, tuple[datetime, int, int]] = {}

    def new_account(self, young: bool) -> str:
        self._account_seq += 1
        author = f"syn-akun-{self._account_seq:05d}"
        if young:
            created = datetime(2026, 10, 10, tzinfo=timezone.utc) + timedelta(seconds=self.rng.randint(0, 14 * 86400))
            followers = self.rng.randint(0, 30)
        else:
            created = datetime(2012, 1, 1, tzinfo=timezone.utc) + timedelta(seconds=self.rng.randint(0, int(14.7 * 365 * 86400)))
            followers = int(self.rng.lognormvariate(4.5, 1.3))
        self.profiles[author] = (created, followers, self.rng.randint(20, 900))
        return author

    def organic_time(self) -> datetime:
        hours = list(range(PERIOD_HOURS))
        weights = [HOURLY_WEIGHT[(PERIOD_START.astimezone(WIB).hour + h) % 24] for h in hours]
        hour = self.rng.choices(hours, weights=weights)[0]
        return PERIOD_START + timedelta(hours=hour, seconds=self.rng.randint(0, 3599))

    def add_post(self, author: str, text: str, posted_at: datetime, urls: tuple[str, ...] = ()) -> None:
        self._post_seq += 1
        created, followers, following = self.profiles[author]
        self.posts.append(
            RawPost(
                platform=PLATFORM,
                author_id=author,
                post_id=f"syn-post-{self._post_seq:06d}",
                text=text,
                posted_at=posted_at,
                account_created_at=created,
                followers=followers,
                following=following,
                urls=urls,
            )
        )

    def organic_text(self) -> tuple[str, tuple[str, ...]]:
        rng = self.rng
        if rng.random() < 0.18:
            text = rng.choice(ORGANIC_SHORT)
        else:
            text = f"{rng.choice(ORGANIC_OPENERS)} {rng.choice(ORGANIC_MIDDLES)}{rng.choice(ORGANIC_ENDINGS)}"
            if rng.random() < 0.25:
                text += f" {rng.choice(ORGANIC_MIDDLES).capitalize()}."
        urls: tuple[str, ...] = ()
        if rng.random() < 0.1:
            url = f"https://{rng.choice(NEWS_DOMAINS)}/berita/{rng.randint(1000, 9999)}"
            text += f" {url}"
            urls = (url,)
        return text, urls


def generate(preset_name: str, seed: int) -> SyntheticDataset:
    return generate_from(PRESETS[preset_name], seed)


def generate_from(preset: Preset, seed: int) -> SyntheticDataset:
    """Dataset dari spesifikasi bebas; dipakai uji ketahanan (radar.analysis.robustness)."""
    b = _Builder(seed)
    rng = b.rng
    dataset = SyntheticDataset(posts=b.posts)

    # Percakapan organik, termasuk satu lonjakan berita yang wajar pukul 12.00 WIB.
    organic = [b.new_account(young=rng.random() < 0.04) for _ in range(preset.organic_accounts)]
    news_burst = PERIOD_START + timedelta(hours=4)
    for author in organic:
        for _ in range(min(1 + int(rng.expovariate(0.9)), 8)):
            text, urls = b.organic_text()
            b.add_post(author, text, b.organic_time(), urls)
        if rng.random() < 0.12:
            text, urls = b.organic_text()
            b.add_post(author, text, news_burst + timedelta(seconds=rng.randint(0, 1800)), urls)

    # Klaster tanaman: muncul serentak di beberapa kejadian berbeda.
    used_slots: set[int] = set()
    for spec in preset.planted:
        members = [b.new_account(young=spec.young_accounts) for _ in range(spec.size)]
        dataset.planted[spec.label] = members
        templates = TALKING_POINTS[spec.label[-1]] if spec.label[-1] in TALKING_POINTS else TALKING_POINTS["A"]

        slots = [s for s in range(1, PERIOD_HOURS * 2 - 1) if s not in used_slots]
        event_slots = sorted(rng.sample(slots, spec.events))
        used_slots.update(event_slots)

        for slot in event_slots:
            event_time = PERIOD_START + timedelta(minutes=30 * slot)
            template = rng.choice(templates)
            for author in members:
                if rng.random() > spec.participation:
                    continue
                text = paraphrase_sentence(template, rng, spec.paraphrase)
                urls: tuple[str, ...] = ()
                if spec.domain and rng.random() < 0.7:
                    url = f"https://{spec.domain}/artikel/{rng.randint(100, 999)}"
                    text += f" {url}"
                    urls = (url,)
                b.add_post(author, text, event_time + timedelta(seconds=rng.randint(0, spec.spread_seconds)), urls)

        # Sedikit aktivitas "normal" agar akun tanaman tidak hanya muncul saat kampanye.
        for author in members:
            for _ in range(rng.randint(0, 2)):
                text, urls = b.organic_text()
                b.add_post(author, text, b.organic_time(), urls)

    # Pengecoh sah: pengumuman resmi yang sama, dibagikan dua kali dari domain lama.
    decoys = [b.new_account(young=False) for _ in range(preset.decoy_size)]
    dataset.decoys = decoys
    url = "https://infosentosa.example/pengumuman/rapat-pedagang"
    for announce_at in (PERIOD_START + timedelta(hours=2), PERIOD_START + timedelta(hours=10)):
        for author in decoys:
            b.add_post(author, f"{DECOY_ANNOUNCEMENT} {url}", announce_at + timedelta(seconds=rng.randint(0, 2400)), (url,))
        for author in decoys:
            if rng.random() < 0.5:
                text, urls = b.organic_text()
                b.add_post(author, text, b.organic_time(), urls)

    return dataset
