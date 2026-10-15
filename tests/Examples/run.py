"""Execute documented examples and verify the displayed code matches its source."""

import re
import subprocess
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
EXPECTED = {
    "fast-forward.php": "Reported: FastForward provider warning\n",
    "reporters.php": "first: Synthetic failure\nsecond: Synthetic failure\n",
    "event-dispatcher.php": "throwable: Synthetic warning\nevent: Synthetic warning\nPrivate log records: 1\n",
    "http-middleware.php": "HTTP 503 | Internal Server Error\nReported: 2\n",
    "http-rendering.php": 'HTTP 503 | application/json\n{"error":"Service unavailable"}\nPrivate reports: 1\n',
    "custom-handlers.php": "PHP errors: 1 | Shutdown notifications: 1\n",
    "scopes.php": "Fresh handlers: yes | Fresh middleware: yes\nHTTP 500 | Reports: 2\n",
}


class Examples(HTMLParser):
    """Collect inline recipe text and validate internal navigation targets."""

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.current = None
        self.code = {}
        self.ids = set()
        self.targets = set()

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if "id" in attrs:
            assert attrs["id"] not in self.ids, "Duplicate HTML id: " + attrs["id"]
            self.ids.add(attrs["id"])
        if attrs.get("href", "").startswith("#"):
            self.targets.add(attrs["href"][1:])
        if tag == "code" and "data-example" in attrs:
            self.current = attrs["data-example"]
            assert self.current not in self.code
            self.code[self.current] = ""

    def handle_data(self, data):
        if self.current is not None:
            self.code[self.current] += data

    def handle_endtag(self, tag):
        if tag == "code":
            self.current = None


page = Examples()
page.feed((ROOT / "docs/index.html").read_text())
assert page.targets <= page.ids, "Broken HTML anchors: " + str(page.targets - page.ids)
cookbook = (ROOT / "docs/examples.md").read_text()
paths = sorted((ROOT / "examples").glob("*.php"))
assert set(p.name for p in paths) == set(EXPECTED) | {"cli.php"}, "Update the example contracts."
for path in paths:
    result = subprocess.run(["php", str(path)], capture_output=True, text=True, timeout=5, cwd=ROOT)
    if path.name == "cli.php":
        assert result.returncode == 1 and result.stdout == ""
        assert "Example warning" in result.stderr and "Example uncaught exception" in result.stderr
    else:
        assert result.returncode == 0 and result.stderr == "", (path.name, result.stderr)
        assert result.stdout == EXPECTED[path.name], (path.name, result.stdout)
    source = re.sub(r"/\*\*[\s\S]*?\*/\n\n", "", path.read_text(), count=1).rstrip()
    key = path.relative_to(ROOT).as_posix()
    assert page.code.get(key) == source, "Stale website example: " + key
    assert "```php\n" + source + "\n```" in cookbook, "Stale cookbook example: " + key
    print("PASS " + key)
assert len(page.code) == len(paths)
print("Eight executable examples, cookbook snippets and HTML anchors verified.")
