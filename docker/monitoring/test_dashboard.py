import unittest
import json
import tempfile
from pathlib import Path
from unittest.mock import patch, MagicMock
import urllib.error
from dashboard import bytes_value, threshold_alerts, slack_delivery

class DashboardTest(unittest.TestCase):
    def test_docker_binary_and_decimal_units(self):
        self.assertEqual(bytes_value('1.5GiB'), 1.5*1024**3)
        self.assertEqual(bytes_value('2.4 MB'), 2400000)
        with self.assertRaises(ValueError): bytes_value('invalid')

    def test_thresholds_identify_the_container_and_host_disk(self):
        snapshot={'containers':[{'name':'tutorlink-app','cpu_percent':81,'memory_percent':30}], 'disk':{'path':'/srv','percent':90}, 'error':None}
        result=threshold_alerts(snapshot,{'cpu_percent':80,'memory_percent':80,'disk_percent':85})
        self.assertEqual([row['metric'] for row in result], ['cpu_percent','disk_percent'])
        self.assertEqual(result[0]['resource'], 'tutorlink-app')

    def test_failed_collector_is_reported(self):
        self.assertEqual(threshold_alerts({'error':'TimeoutExpired'}, {})[0]['metric'], 'collector')

class SlackDeliveryTest(unittest.TestCase):
    def test_success_saves_original_slack_response_without_request_secrets(self):
        raw = b'{"ok":true,"channel":"C123456789","ts":"123.456","message":{"text":"TEST"}}'
        response = MagicMock()
        response.__enter__.return_value = response
        response.read.return_value = raw
        response.status = 200
        response.headers = {'x-slack-req-id': 'request-id'}
        with tempfile.TemporaryDirectory() as directory, patch('dashboard.slack_open', return_value=response) as send:
            result = slack_delivery({'metric': 'cpu_percent', 'resource': 'test', 'value': 81, 'threshold': 80}, 'secret-token', 'C123456789', Path(directory), test=True)
            self.assertEqual(result['status'], 'sent')
            self.assertEqual((Path(directory)/result['response_file']).read_bytes(), raw)
            self.assertNotIn('secret-token', json.dumps(result))
            payload = json.loads(send.call_args.args[0].data)
            self.assertIn('TEST threshold alert', payload['text'])

    def test_slack_ok_false_is_not_recorded_as_delivery(self):
        response = MagicMock()
        response.__enter__.return_value = response
        response.read.return_value = b'{"ok":false,"error":"invalid_auth"}'
        with tempfile.TemporaryDirectory() as directory, patch('dashboard.slack_open', return_value=response):
            result = slack_delivery({'metric': 'cpu_percent', 'resource': 'test'}, 'secret', 'channel', Path(directory))
            self.assertEqual(result['status'], 'failed')
            self.assertEqual(list(Path(directory).iterdir()), [])

    def test_rate_limit_preserves_retry_after(self):
        error = urllib.error.HTTPError('https://slack.com/api/chat.postMessage', 429, 'rate limited', {'Retry-After': '120'}, None)
        with tempfile.TemporaryDirectory() as directory, patch('dashboard.slack_open', side_effect=error):
            result = slack_delivery({'metric': 'cpu_percent', 'resource': 'test'}, 'secret', 'channel', Path(directory))
            self.assertEqual(result['retry_after'], 120)
            self.assertEqual(result['status'], 'failed')

if __name__=='__main__': unittest.main()
