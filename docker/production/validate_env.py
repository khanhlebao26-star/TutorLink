#!/usr/bin/env python3
"""Validate Vercel + VPS deployment settings without printing secret values."""
import argparse
import base64
from pathlib import Path
from urllib.parse import urlparse


def read_env(path):
    values = {}
    for line in Path(path).read_text().splitlines():
        line = line.strip()
        if line and not line.startswith('#') and '=' in line:
            key, value = line.split('=', 1)
            values[key] = value.strip().strip('"').strip("'")
    return values


def validate(values):
    errors = []
    for key in ['APP_KEY', 'DB_PASSWORD', 'REVERB_APP_KEY', 'REVERB_APP_SECRET', 'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'RELEASE_TAG']:
        value = values.get(key, '')
        if not value or 'replace-' in value or 'example.com' in value:
            errors.append(f'{key} must be configured')
    for key, required in {'APP_ENV':'production','APP_DEBUG':'false','SESSION_DRIVER':'database','QUEUE_CONNECTION':'database','SESSION_SECURE_COOKIE':'true','SESSION_SAME_SITE':'lax'}.items():
        if values.get(key) != required:
            errors.append(f'{key} must be {required}')
    try:
        key = values.get('APP_KEY', '')
        if not key.startswith('base64:') or len(base64.b64decode(key[7:], validate=True)) != 32:
            errors.append('APP_KEY must be a 32-byte base64 Laravel key')
    except ValueError:
        errors.append('APP_KEY has invalid base64 encoding')
    api, frontend = urlparse(values.get('APP_URL','')), urlparse(values.get('FRONTEND_URL',''))
    for name, url in [('APP_URL', api), ('FRONTEND_URL', frontend)]:
        if url.scheme != 'https' or not url.hostname or url.username or url.password or url.path not in ['', '/']:
            errors.append(f'{name} must be an HTTPS origin without credentials')
    domain = values.get('SESSION_DOMAIN','').lstrip('.')
    if not domain or not api.hostname or not frontend.hostname or not all(h == domain or h.endswith('.'+domain) for h in [api.hostname,frontend.hostname]):
        errors.append('SESSION_DOMAIN must cover the API and frontend custom domains')
    if values.get('APP_DOMAIN') != api.hostname:
        errors.append('APP_DOMAIN must match APP_URL')
    if frontend.netloc not in values.get('SANCTUM_STATEFUL_DOMAINS','').split(','):
        errors.append('SANCTUM_STATEFUL_DOMAINS must include the frontend host')
    if frontend.hostname not in values.get('REVERB_ALLOWED_ORIGINS','').split(','):
        errors.append('REVERB_ALLOWED_ORIGINS must include the frontend host')
    return errors


if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('environment',type=Path)
    args=parser.parse_args()
    errors=validate(read_env(args.environment))
    for error in errors: print(error)
    print('Deployment environment: '+('FAIL' if errors else 'PASS'))
    raise SystemExit(bool(errors))
