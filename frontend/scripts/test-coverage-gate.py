import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

class CoverageGateTest(unittest.TestCase):
    def gate(self, frontend, backend):
        with tempfile.TemporaryDirectory() as temporary:
            root=Path(temporary)
            (root/'frontend.json').write_text(json.dumps({'total':{'lines':{'pct':frontend}}}))
            (root/'backend.xml').write_text(f'<coverage><project><metrics statements="100" coveredstatements="{backend}"/></project></coverage>')
            return subprocess.run([sys.executable,str(Path(__file__).with_name('check-codebase-coverage.py')),str(root/'frontend.json'),str(root/'backend.xml')],capture_output=True,text=True).returncode
    def test_both_must_exceed_seventy(self): self.assertEqual(self.gate(70.01,71),0)
    def test_frontend_seventy_does_not_pass(self): self.assertNotEqual(self.gate(70,99),0)
    def test_backend_seventy_does_not_pass(self): self.assertNotEqual(self.gate(99,70),0)
    def test_empty_backend_does_not_pass(self): self.assertNotEqual(self.gate(99,0),0)

if __name__=='__main__':unittest.main()
