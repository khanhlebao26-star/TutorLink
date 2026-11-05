import json
import sys
import xml.etree.ElementTree as ET
from pathlib import Path
if len(sys.argv)!=3:
    raise SystemExit("Pass frontend coverage-summary.json and backend clover.xml")
frontend=json.loads(Path(sys.argv[1]).read_text())["total"]["lines"]["pct"]
metrics=ET.parse(sys.argv[2]).find("./project/metrics")
if metrics is None:
    raise SystemExit("Clover project metrics missing")
statements=int(metrics.attrib["statements"])
backend=100*int(metrics.attrib["coveredstatements"])/statements if statements else 0
print(json.dumps({"frontend_lines":frontend,"backend_statements":backend,"required":"each >70%"}))
raise SystemExit(0 if isinstance(frontend,(int,float)) and frontend>70 and backend>70 else 1)
