#!/usr/bin/env python3
"""Measure production HTTP response latency for the public prerender routes."""

import argparse
import math
import statistics
import time
from urllib.request import urlopen


def measure(base_url: str, path: str, samples: int) -> None:
    durations = []
    body_size = 0
    status = 0
    for _ in range(samples):
        started = time.perf_counter()
        with urlopen(f"{base_url.rstrip('/')}{path}", timeout=15) as response:
            body = response.read()
            status = response.status
        durations.append((time.perf_counter() - started) * 1000)
        body_size = len(body)

    ordered = sorted(durations)
    p95 = ordered[max(0, math.ceil(samples * 0.95) - 1)]
    print(
        f"{path}: status={status}, samples={samples}, "
        f"p50={statistics.median(durations):.1f}ms, p95={p95:.1f}ms, "
        f"body={body_size} bytes"
    )


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://localhost:3100")
    parser.add_argument("--tutor-slug", required=True)
    parser.add_argument("--samples", type=int, default=12)
    args = parser.parse_args()
    if args.samples < 1:
        parser.error("--samples must be at least 1")

    for path in (
        "/",
        "/about",
        "/become-a-tutor",
        f"/tutors/{args.tutor_slug}",
    ):
        measure(args.base_url, path, args.samples)


if __name__ == "__main__":
    main()
