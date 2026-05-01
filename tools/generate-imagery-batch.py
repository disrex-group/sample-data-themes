#!/usr/bin/env python3
"""
Standalone Replicate batch runner for the home-living theme.

Reads tools/image-prompts/home-living.json (Stage 1 / 2 manifest) and
tools/image-prompts/home-living-phase2.json (Phase 2 manifest with 204
new lines). For each entry whose target image file is missing on disk,
fires a Replicate prediction (Flux 2 Pro for studios, Seedream 4.5 for
scenes), waits for the URL, and downloads the image to its target path.

Concurrency is bounded by a semaphore so we never exceed ~8 in-flight
calls. Re-running the script is idempotent: any image already on disk
is skipped, so partial-failure resumes are free.

Usage:
    REPLICATE_API_TOKEN=r8_xxx python3 tools/generate-imagery-batch.py [options]

Options:
    --dry-run         List what would be generated; don't fire anything.
    --studios-only    Only fire studio shots; skip scenes.
    --scenes-only     Only fire scene shots; skip studios.
    --limit=N         Cap generations at N images (after dry-run filter).
    --concurrency=N   Max concurrent predictions (default 8).
    --skip-existing   Skip if the target file already exists (default).
    --force           Regenerate even if the target file exists.

The script prints a one-line progress update every time a prediction
completes, plus a per-leaf summary at the end.

Exit codes:
    0   All targets generated (or already present) successfully.
    1   One or more failures; check the printed summary.
    2   Configuration error (missing token, missing manifest).
"""

from __future__ import annotations

import argparse
import asyncio
import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
MANIFEST_PRIMARY = ROOT / 'tools' / 'image-prompts' / 'home-living.json'
MANIFEST_PHASE2 = ROOT / 'tools' / 'image-prompts' / 'home-living-phase2.json'
MANIFEST_DEDUP = ROOT / 'tools' / 'image-prompts' / 'home-living-dedup.json'
MANIFEST_DEDUP2 = ROOT / 'tools' / 'image-prompts' / 'home-living-dedup-round2.json'
IMAGES_DIR = ROOT / 'packages' / 'theme-home-living-media' / '_files' / 'images'
SCENES_DIR = ROOT / 'packages' / 'theme-home-living-media' / '_files' / 'scenes'

REPLICATE_API = 'https://api.replicate.com/v1'
STUDIO_MODEL = 'black-forest-labs/flux-2-pro'
SCENE_MODEL = 'bytedance/seedream-4.5'


def die(msg: str, code: int = 2) -> None:
    print(f'ERROR: {msg}', file=sys.stderr)
    sys.exit(code)


# ---------------------------------------------------------------------------
# Manifest loading and prompt assembly
# ---------------------------------------------------------------------------

def load_manifests() -> tuple[dict[str, Any], dict[str, Any]]:
    """Load both manifests. Returns (anchors_and_conventions, products).

    `products` is a flat dict keyed by SKU; entries from phase2 are merged
    on top of the primary manifest, so a Phase-2 entry with the same SKU
    overrides the primary.
    """
    if not MANIFEST_PRIMARY.exists():
        die(f'manifest missing: {MANIFEST_PRIMARY}')
    primary = json.loads(MANIFEST_PRIMARY.read_text())
    anchors_conv = {
        'anchors': primary.get('anchors', {}),
        'camera_conventions': primary.get('camera_conventions', {}),
        'models': primary.get('models', {}),
    }
    products = dict(primary.get('products', {}))
    if MANIFEST_PHASE2.exists():
        ph2 = json.loads(MANIFEST_PHASE2.read_text())
        for sku, entry in ph2.get('products', {}).items():
            products[sku] = entry
    if MANIFEST_DEDUP.exists():
        # Dedup manifest entries reuse the SAME sku as the simple they
        # replace imagery for, but produce a *new* studio filename. We
        # need to merge them as a separate "image record" rather than
        # overwriting the existing product entry. Use a synthetic sku
        # like "<orig-sku>::dedup" so the runner's plan_jobs sees them
        # as separate jobs.
        dedup = json.loads(MANIFEST_DEDUP.read_text())
        for sku, entry in dedup.get('products', {}).items():
            products[f'{sku}::dedup'] = entry
    if MANIFEST_DEDUP2.exists():
        dedup2 = json.loads(MANIFEST_DEDUP2.read_text())
        for sku, entry in dedup2.get('products', {}).items():
            products[f'{sku}::dedup2'] = entry
    return anchors_conv, products


def build_studio_prompt(anchors: dict, conv: dict, product: dict) -> str:
    """Compose the Flux 2 Pro studio prompt: prefix + style + camera + body + suffix."""
    cam_key = product.get('camera_category')
    cam = conv.get(cam_key, {}).get('language', '')
    return (
        anchors.get('studio_style_prefix', '')
        + anchors.get('studio_style', '')
        + ' '
        + cam
        + ' '
        + product['studio']['prompt']
        + anchors.get('studio_style_suffix', '')
    )


# ---------------------------------------------------------------------------
# Replicate HTTP client
# ---------------------------------------------------------------------------

class ReplicateClient:
    """Minimal Replicate API client over urllib (no external deps)."""

    def __init__(self, token: str) -> None:
        self.token = token
        self._headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json',
            'Prefer': 'wait',
        }

    async def predict_model(
        self,
        model_owner: str,
        model_name: str,
        input_payload: dict,
        timeout_s: float = 90.0,
    ) -> dict:
        """Fire a Replicate prediction on an official model and wait for output.

        We use Replicate's `Prefer: wait` header so the response blocks
        until the prediction finishes (up to ~60s server-side). For longer
        runs the response can come back with status='starting' or
        'processing' — in that case we poll the prediction id.
        """
        url = f'{REPLICATE_API}/models/{model_owner}/{model_name}/predictions'
        body = json.dumps({'input': input_payload}).encode('utf-8')
        loop = asyncio.get_event_loop()
        # Initial create-and-wait
        resp = await loop.run_in_executor(None, self._post_json, url, body)
        if resp.get('status') == 'succeeded':
            return resp
        # Poll until complete
        prediction_id = resp['id']
        deadline = time.time() + timeout_s
        while time.time() < deadline:
            await asyncio.sleep(2.0)
            poll = await loop.run_in_executor(
                None, self._get_json, f'{REPLICATE_API}/predictions/{prediction_id}'
            )
            if poll.get('status') == 'succeeded':
                return poll
            if poll.get('status') in ('failed', 'canceled'):
                raise RuntimeError(
                    f'prediction {prediction_id} ended in status '
                    f'{poll["status"]}: {poll.get("error")}'
                )
        raise TimeoutError(
            f'prediction {prediction_id} did not complete within {timeout_s}s'
        )

    def _post_json(self, url: str, body: bytes) -> dict:
        req = urllib.request.Request(url, data=body, headers=self._headers, method='POST')
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                return json.loads(r.read().decode('utf-8'))
        except urllib.error.HTTPError as e:
            err_body = e.read().decode('utf-8', errors='replace')
            raise RuntimeError(f'HTTP {e.code} from Replicate: {err_body}') from e

    def _get_json(self, url: str) -> dict:
        req = urllib.request.Request(url, headers=self._headers, method='GET')
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read().decode('utf-8'))


def download_to(url: str, dest: Path) -> None:
    """Stream a Replicate output URL to disk."""
    dest.parent.mkdir(parents=True, exist_ok=True)
    with urllib.request.urlopen(url, timeout=120) as r, open(dest, 'wb') as f:
        while True:
            chunk = r.read(64 * 1024)
            if not chunk:
                break
            f.write(chunk)


# ---------------------------------------------------------------------------
# Job planning
# ---------------------------------------------------------------------------

def plan_jobs(
    products: dict,
    *,
    studios: bool,
    scenes: bool,
    skip_existing: bool,
    limit: int | None,
) -> tuple[list[dict], list[dict]]:
    """Return (studio_jobs, scene_jobs). A job is a dict with the fields
    needed to fire and place the image."""
    studio_jobs: list[dict] = []
    scene_jobs: list[dict] = []

    for sku, p in products.items():
        # Skip Tier-B entries that have no per-SKU studio (variants share the parent's image).
        if studios and 'studio' in p:
            fname = p['studio']['filename']
            target = IMAGES_DIR / fname
            if not (skip_existing and target.exists()):
                studio_jobs.append({
                    'kind': 'studio',
                    'sku': sku,
                    'category': p.get('category', ''),
                    'camera_category': p.get('camera_category', ''),
                    'filename': fname,
                    'target_path': target,
                    'studio': p['studio'],
                    'source_studio_filename': fname,  # studios use themselves as image_input source for scenes
                })
        if scenes and 'scenes' in p:
            for scn in p['scenes']:
                fname = scn['filename']
                target = SCENES_DIR / fname
                if not (skip_existing and target.exists()):
                    scene_jobs.append({
                        'kind': 'scene',
                        'sku': sku,
                        'category': p.get('category', ''),
                        'filename': fname,
                        'target_path': target,
                        'scene': scn,
                        'source_studio_filename': p.get('studio', {}).get('filename', ''),
                    })

    if limit is not None:
        # Cap each list proportionally so we generate a representative slice.
        studio_cap = (limit + 1) // 2
        scene_cap = limit - len(studio_jobs[:studio_cap])
        studio_jobs = studio_jobs[:studio_cap]
        scene_jobs = scene_jobs[:max(0, scene_cap)]

    return studio_jobs, scene_jobs


# ---------------------------------------------------------------------------
# Worker coroutines
# ---------------------------------------------------------------------------

async def run_studio_job(
    job: dict,
    client: ReplicateClient,
    anchors: dict,
    conventions: dict,
    sem: asyncio.Semaphore,
    stats: dict,
) -> None:
    async with sem:
        try:
            prompt = build_studio_prompt(anchors, conventions, {
                'studio': job['studio'],
                'camera_category': job['camera_category'],
            })
            resp = await client.predict_model(
                'black-forest-labs', 'flux-2-pro',
                {
                    'prompt': prompt,
                    'aspect_ratio': '1:1',
                    'output_format': 'jpg',
                    'output_quality': 90,
                    'safety_tolerance': 2,
                },
            )
            output_url = resp.get('output')
            if isinstance(output_url, list):
                output_url = output_url[0]
            if not output_url:
                raise RuntimeError(f'no output url in response: {resp}')
            await asyncio.get_event_loop().run_in_executor(
                None, download_to, output_url, job['target_path']
            )
            stats['done'] += 1
            print(f'[{stats["done"]:3d}/{stats["total"]:3d}] studio  {job["filename"]}')
        except Exception as e:
            stats['failed'].append((job['filename'], str(e)))
            print(f'  FAIL studio {job["filename"]}: {e}', file=sys.stderr)


async def run_scene_job(
    job: dict,
    client: ReplicateClient,
    sem: asyncio.Semaphore,
    stats: dict,
) -> None:
    async with sem:
        try:
            # Scenes use the source studio image as image_input (Seedream 4.5
            # image-to-image conditioning). The source must already exist on disk.
            studio_path = IMAGES_DIR / job['source_studio_filename']
            if not studio_path.exists():
                raise RuntimeError(
                    f'source studio missing on disk: {studio_path} '
                    f'(scene job for {job["filename"]} cannot run yet)'
                )
            # Upload the local studio image to Replicate's file store and use the
            # returned URL. Seedream 4.5 accepts http(s) URLs only.
            upload_url = await asyncio.get_event_loop().run_in_executor(
                None, _upload_file_for_image_input, client, studio_path
            )
            resp = await client.predict_model(
                'bytedance', 'seedream-4.5',
                {
                    'prompt': job['scene']['prompt'],
                    'image_input': [upload_url],
                    'aspect_ratio': '3:2',
                    'size': '2K',
                },
            )
            output_url = resp.get('output')
            if isinstance(output_url, list):
                output_url = output_url[0]
            if not output_url:
                raise RuntimeError(f'no output url in response: {resp}')
            await asyncio.get_event_loop().run_in_executor(
                None, download_to, output_url, job['target_path']
            )
            stats['done'] += 1
            print(f'[{stats["done"]:3d}/{stats["total"]:3d}] scene   {job["filename"]}')
        except Exception as e:
            stats['failed'].append((job['filename'], str(e)))
            print(f'  FAIL scene {job["filename"]}: {e}', file=sys.stderr)


def _upload_file_for_image_input(client: ReplicateClient, path: Path) -> str:
    """Upload a local file to Replicate's `files` endpoint and return its URL.

    Seedream 4.5's `image_input` accepts public URLs only. The public-internet
    URL of `app.disrex-flex-two.test/.../sofa-helsinki-001.jpg` won't resolve
    from Replicate's GPU pods, so we upload to Replicate's CDN and use that.
    """
    boundary = f'----disrex{int(time.time()*1000)}'
    body = []
    body.append(f'--{boundary}')
    body.append(f'Content-Disposition: form-data; name="content"; filename="{path.name}"')
    body.append('Content-Type: image/jpeg')
    body.append('')
    parts = ('\r\n'.join(body) + '\r\n').encode('utf-8')
    parts += path.read_bytes()
    parts += f'\r\n--{boundary}--\r\n'.encode('utf-8')

    req = urllib.request.Request(
        f'{REPLICATE_API}/files',
        data=parts,
        headers={
            'Authorization': f'Bearer {client.token}',
            'Content-Type': f'multipart/form-data; boundary={boundary}',
        },
        method='POST',
    )
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            payload = json.loads(r.read().decode('utf-8'))
            return payload['urls']['get']
    except urllib.error.HTTPError as e:
        err_body = e.read().decode('utf-8', errors='replace')
        raise RuntimeError(f'HTTP {e.code} uploading file: {err_body}') from e


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

async def main_async(args: argparse.Namespace) -> int:
    token = os.environ.get('REPLICATE_API_TOKEN', '').strip()
    if not token:
        die('REPLICATE_API_TOKEN not set in environment.\n'
            'Set it with:  export REPLICATE_API_TOKEN=r8_yourtokenhere')

    anchors_conv, products = load_manifests()
    anchors = anchors_conv.get('anchors', {})
    conventions = anchors_conv.get('camera_conventions', {})

    studio_jobs, scene_jobs = plan_jobs(
        products,
        studios=not args.scenes_only,
        scenes=not args.studios_only,
        skip_existing=not args.force,
        limit=args.limit,
    )
    print(f'Plan: {len(studio_jobs)} studio shots, {len(scene_jobs)} scene shots')
    if args.dry_run:
        for j in studio_jobs[:5]:
            print(f'  studio (sample) {j["filename"]} -> {j["target_path"]}')
        for j in scene_jobs[:5]:
            print(f'  scene (sample)  {j["filename"]} -> {j["target_path"]}')
        return 0

    client = ReplicateClient(token)
    sem = asyncio.Semaphore(args.concurrency)
    stats = {
        'done': 0,
        'total': len(studio_jobs) + len(scene_jobs),
        'failed': [],
    }

    # Run studio jobs first — scenes need their source studios on disk.
    if studio_jobs:
        print(f'--- studios: {len(studio_jobs)} jobs, concurrency={args.concurrency} ---')
        await asyncio.gather(*[
            run_studio_job(j, client, anchors, conventions, sem, stats)
            for j in studio_jobs
        ])
    if scene_jobs:
        print(f'--- scenes: {len(scene_jobs)} jobs, concurrency={args.concurrency} ---')
        await asyncio.gather(*[
            run_scene_job(j, client, sem, stats)
            for j in scene_jobs
        ])

    print(f'\nDone: {stats["done"]}/{stats["total"]} succeeded, '
          f'{len(stats["failed"])} failed')
    if stats['failed']:
        for fname, err in stats['failed'][:20]:
            print(f'  - {fname}: {err}')
        return 1
    return 0


def parse_args() -> argparse.Namespace:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument('--dry-run', action='store_true')
    ap.add_argument('--studios-only', action='store_true')
    ap.add_argument('--scenes-only', action='store_true')
    ap.add_argument('--limit', type=int, default=None)
    ap.add_argument('--concurrency', type=int, default=8)
    ap.add_argument('--force', action='store_true',
                    help='regenerate even if target file already exists')
    return ap.parse_args()


if __name__ == '__main__':
    args = parse_args()
    try:
        sys.exit(asyncio.run(main_async(args)))
    except KeyboardInterrupt:
        print('\nInterrupted.')
        sys.exit(130)
