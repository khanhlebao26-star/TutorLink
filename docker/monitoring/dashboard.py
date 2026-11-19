#!/usr/bin/env python3
"""Local-only Docker resource dashboard and webhook receiver; no third-party packages."""
import argparse
import datetime
import json
import shutil
import subprocess
import threading
import time
import urllib.request
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


def bytes_value(text):
    text = text.strip().replace(' ', '')
    for unit, factor in [('TiB', 1024**4), ('GiB', 1024**3), ('MiB', 1024**2), ('KiB', 1024), ('GB', 1000**3), ('MB', 1000**2), ('kB', 1000), ('B', 1)]:
        if text.endswith(unit):
            return float(text[:-len(unit)]) * factor
    raise ValueError('Unknown byte unit')


def collect(prefix, disk_path):
    result = subprocess.run(['docker', 'stats', '--no-stream', '--format', '{{json .}}'], capture_output=True, text=True, timeout=15, check=True)
    containers = []
    for line in result.stdout.splitlines():
        row = json.loads(line)
        if not row['Name'].startswith(prefix):
            continue
        used, limit = row['MemUsage'].split('/')
        received, sent = row['NetIO'].split('/')
        containers.append({'name': row['Name'], 'cpu_percent': float(row['CPUPerc'].rstrip('%')), 'memory_percent': float(row['MemPerc'].rstrip('%')), 'memory_bytes': bytes_value(used), 'memory_limit_bytes': bytes_value(limit), 'network_rx_bytes': bytes_value(received), 'network_tx_bytes': bytes_value(sent)})
    disk = shutil.disk_usage(disk_path)
    return {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'containers': containers, 'disk': {'path': str(disk_path), 'used_bytes': disk.used, 'total_bytes': disk.total, 'percent': disk.used / disk.total * 100}, 'error': None}


def threshold_alerts(snapshot, limits):
    alerts = []
    if snapshot.get('error'):
        return [{'metric': 'collector', 'resource': 'docker', 'message': snapshot['error']}]
    for row in snapshot['containers']:
        for metric in ['cpu_percent', 'memory_percent']:
            if row[metric] >= limits[metric]:
                alerts.append({'metric': metric, 'resource': row['name'], 'value': row[metric], 'threshold': limits[metric]})
    if snapshot['disk']['percent'] >= limits['disk_percent']:
        alerts.append({'metric': 'disk_percent', 'resource': snapshot['disk']['path'], 'value': snapshot['disk']['percent'], 'threshold': limits['disk_percent']})
    return alerts


HTML = '''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>TutorLink resources</title><style>body{font:16px system-ui;background:#f8f7ff;color:#071a4c;max-width:1100px;margin:40px auto;padding:0 24px}h1{font-size:36px}small{color:#64748b}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}article,table{background:white;border-radius:16px;padding:24px;border:1px solid #dce2f4}strong{display:block;font-size:30px;color:#e50085}table{margin-top:24px;width:100%;text-align:left}td,th{padding:12px;border-bottom:1px solid #eee}pre{white-space:pre-wrap}.warn{color:#b42318}</style><h1>TutorLink · Resources</h1><p id="updated">Connecting…</p><p><small>Docker CPU/RAM and cumulative container network I/O. Disk refers to the filesystem on the machine running this collector. On macOS, container limits refer to the Docker VM.</small></p><div class="cards"><article>Container CPU sum<strong id="cpu">—</strong></article><article>Container memory sum<strong id="ram">—</strong></article><article>Host disk used<strong id="disk">—</strong></article><article>Network RX / TX<strong id="net">—</strong></article></div><table><thead><tr><th>Container</th><th>CPU</th><th>RAM</th><th>RX / TX</th></tr></thead><tbody id="rows"></tbody></table><h2>Threshold delivery</h2><p id="limits"></p><pre id="alerts"></pre><script>const mb=n=>(n/1048576).toFixed(1)+' MB';async function refresh(){try{let d=await(await fetch('/api/metrics')).json();document.querySelector('#updated').textContent=d.checked_at+(d.error?' · '+d.error:'');document.querySelector('#cpu').textContent=d.containers.reduce((s,c)=>s+c.cpu_percent,0).toFixed(1)+'%';document.querySelector('#ram').textContent=mb(d.containers.reduce((s,c)=>s+c.memory_bytes,0));document.querySelector('#disk').textContent=d.disk?d.disk.percent.toFixed(1)+'%':'—';document.querySelector('#net').textContent=mb(d.containers.reduce((s,c)=>s+c.network_rx_bytes,0))+' / '+mb(d.containers.reduce((s,c)=>s+c.network_tx_bytes,0));let root=document.querySelector('#rows');root.replaceChildren();for(let c of d.containers){let tr=document.createElement('tr');for(let text of [c.name,c.cpu_percent.toFixed(1)+'%',mb(c.memory_bytes),mb(c.network_rx_bytes)+' / '+mb(c.network_tx_bytes)]){let td=document.createElement('td');td.textContent=text;tr.append(td)}root.append(tr)}document.querySelector('#limits').textContent=JSON.stringify(d.limits);document.querySelector('#alerts').textContent=JSON.stringify(d.deliveries,null,2)}catch(e){document.querySelector('#updated').textContent='Collector unavailable'} }refresh();setInterval(refresh,5000)</script></html>'''


def serve(args):
    state = {'checked_at': None, 'containers': [], 'disk': None, 'error': 'Starting collector', 'limits': {'cpu_percent': args.cpu, 'memory_percent': args.memory, 'disk_percent': args.disk}, 'deliveries': []}
    mutex = threading.Lock()
    args.output.mkdir(parents=True, exist_ok=True)

    class Handler(BaseHTTPRequestHandler):
        def do_GET(self):
            if self.path not in ['/', '/api/metrics']:
                self.send_error(404)
                return
            with mutex:
                body = HTML.encode() if self.path == '/' else json.dumps(state).encode()
            self.send_response(200)
            self.send_header('Content-Type', 'text/html; charset=utf-8' if self.path == '/' else 'application/json')
            self.send_header('Cache-Control', 'no-store')
            self.end_headers()
            self.wfile.write(body)

        def do_POST(self):
            if self.path != '/alerts' or int(self.headers.get('Content-Length', '0')) > 16384:
                self.send_error(400)
                return
            try:
                alert = json.loads(self.rfile.read(int(self.headers.get('Content-Length', '0'))))
                if not isinstance(alert, dict) or 'metric' not in alert:
                    raise ValueError('Invalid alert')
                alert['received_at'] = datetime.datetime.now(datetime.timezone.utc).isoformat()
                with mutex:
                    state['deliveries'] = (state['deliveries'] + [alert])[-20:]
                    with (args.output / 'received-alerts.jsonl').open('a') as stream:
                        stream.write(json.dumps(alert)+'\n')
                self.send_response(204)
                self.end_headers()
            except (ValueError, TypeError):
                self.send_error(400)

        def log_message(self, format, *values):
            pass

    def poll():
        last_sent = {}
        while True:
            try:
                snapshot = collect(args.prefix, args.disk_path)
            except (subprocess.SubprocessError, ValueError, OSError) as error:
                snapshot = {'checked_at': datetime.datetime.now(datetime.timezone.utc).isoformat(), 'containers': [], 'disk': None, 'error': type(error).__name__}
            with mutex:
                state.update(snapshot)
                (args.output / 'latest.json').write_text(json.dumps(state, indent=2))
            for alert in threshold_alerts(snapshot, state['limits']):
                key = (alert['metric'], alert['resource'])
                if time.monotonic() - last_sent.get(key, -1e9) < 60:
                    continue
                try:
                    req = urllib.request.Request(f'http://127.0.0.1:{args.port}/alerts', data=json.dumps(alert).encode(), headers={'Content-Type': 'application/json'})
                    with urllib.request.urlopen(req, timeout=3):
                        last_sent[key] = time.monotonic()
                except OSError:
                    pass
            time.sleep(5)

    server = ThreadingHTTPServer(('127.0.0.1', args.port), Handler)
    threading.Thread(target=poll, daemon=True).start()
    print(f'TutorLink dashboard: http://127.0.0.1:{args.port}', flush=True)
    server.serve_forever()


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--prefix', default='tutorlink-')
    parser.add_argument('--port', type=int, default=9070)
    parser.add_argument('--cpu', type=float, default=80)
    parser.add_argument('--memory', type=float, default=80)
    parser.add_argument('--disk', type=float, default=80)
    parser.add_argument('--disk-path', type=Path, default=Path.cwd())
    parser.add_argument('--output', type=Path, default=Path('outputs/deploy-readiness/monitoring'))
    serve(parser.parse_args())
