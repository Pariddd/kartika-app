import pytest

from radar.payload import PayloadError, validate_collect_params, validate_envelope


def envelope(**overrides):
    base = {
        "schema_version": 1,
        "job_type": "collect",
        "case_id": 7,
        "requested_by": 3,
        "params": {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": 100},
    }
    base.update(overrides)
    return base


def test_envelope_valid():
    assert validate_envelope(envelope(), job_type="collect", case_id=7)["case_id"] == 7


@pytest.mark.parametrize(
    "payload",
    [
        "bukan objek",
        envelope(schema_version=2),
        envelope(job_type="analyze"),
        envelope(case_id=8),
        envelope(requested_by="3"),
        envelope(requested_by=True),
        envelope(params=[]),
    ],
)
def test_envelope_ditolak(payload):
    with pytest.raises(PayloadError):
        validate_envelope(payload, job_type="collect", case_id=7)


def test_youtube_valid():
    params = {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ", "9bZkp7q19f0"], "max_items": 5000}
    assert validate_collect_params(params) is params


def test_x_valid():
    params = {"platform": "x", "query": "#Tagar", "max_items": 1000, "since": "2026-11-01", "until": None}
    assert validate_collect_params(params) is params


@pytest.mark.parametrize(
    "params",
    [
        {"platform": "telegram"},
        {"platform": "youtube", "video_ids": [], "max_items": 10},
        {"platform": "youtube", "video_ids": ["a" * 11] * 11, "max_items": 10},
        {"platform": "youtube", "video_ids": ["pendek"], "max_items": 10},
        {"platform": "youtube", "video_ids": ["dQw4w9WgXc<"], "max_items": 10},
        {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": 5001},
        {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": 0},
        {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": "10"},
        {"platform": "youtube", "video_ids": ["dQw4w9WgXcQ"], "max_items": True},
        {"platform": "x", "query": "", "max_items": 10},
        {"platform": "x", "query": "a" * 201, "max_items": 10},
        {"platform": "x", "query": "#T", "max_items": 1001},
        {"platform": "x", "query": "#T", "max_items": 10, "since": "02/11/2026"},
        {"platform": "x", "query": "#T", "max_items": 10, "since": "2026-11-02", "until": "2026-11-01"},
    ],
)
def test_collect_params_ditolak(params):
    with pytest.raises(PayloadError):
        validate_collect_params(params)


def test_sintetis_valid():
    params = {"platform": "synthetic", "preset": "standar", "seed": 42}
    assert validate_collect_params(params) is params


@pytest.mark.parametrize(
    "params",
    [
        {"platform": "synthetic", "preset": "rahasia", "seed": 1},
        {"platform": "synthetic", "preset": "standar", "seed": -1},
        {"platform": "synthetic", "preset": "standar", "seed": "42"},
        {"platform": "synthetic", "preset": "standar"},
    ],
)
def test_sintetis_ditolak(params):
    with pytest.raises(PayloadError):
        validate_collect_params(params)
