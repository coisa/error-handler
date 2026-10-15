"""Verify a Git archive contains only runtime distribution files and installs."""

import io
import subprocess
import tarfile
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
payload = subprocess.check_output(["git", "archive", "--format=tar", "HEAD"], cwd=ROOT)
allowed = {"composer.json", "LICENSE", "README.md", "CHANGELOG.md"}
with tarfile.open(fileobj=io.BytesIO(payload)) as archive:
    paths = {member.name for member in archive.getmembers() if member.isfile()}
    unexpected = {path for path in paths if path not in allowed and not path.startswith("src/")}
    assert not unexpected, "Development files leaked into the archive: " + str(unexpected)
    assert allowed <= paths and "src/ServiceProvider/ErrorHandlerServiceProvider.php" in paths
    with tempfile.TemporaryDirectory(prefix="error-handler-distribution-") as directory:
        # Every archive entry comes from this trusted Git commit, with checked paths.
        archive.extractall(directory)
        subprocess.run([
            "composer", "install", "--no-dev", "--no-plugins", "--no-scripts",
            "--no-interaction", "--prefer-dist", "--no-progress",
        ], cwd=directory, check=True, timeout=120)
        code = r'''
require 'vendor/autoload.php';
$provider = new CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider();
$services = FastForward\Container\container($provider);
$handler = $services->get(CoiSA\ErrorHandler\ErrorHandlerInterface::class);
if (!$handler instanceof CoiSA\ErrorHandler\ErrorHandler || count($provider->getFactories()) !== 9) {
    throw new RuntimeException('Production archive provider resolution failed.');
}
if (class_exists(CoiSA\ErrorHandler\Container\ConfigProvider::class)) {
    throw new RuntimeException('Legacy ConfigProvider remains available.');
}
if (!class_exists(CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory::class)
    || !class_exists(CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware::class)) {
    throw new RuntimeException('Production HTTP adapters cannot load without development dependencies.');
}
'''
        subprocess.run(["php", "-r", code], cwd=directory, check=True, timeout=5)
print("PASS runtime archive exclusions and production-only Composer/provider autoload")
