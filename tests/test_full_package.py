"""Safety and distribution boundaries for assembling the supplied full edition."""
import hashlib
import importlib.util
import stat
import tempfile
import unittest
import zipfile
from pathlib import Path

spec = importlib.util.spec_from_file_location('full_package', Path(__file__).resolve().parents[1] / 'scripts/package_full.py')
full = importlib.util.module_from_spec(spec)
spec.loader.exec_module(full)


class FullPackageTest(unittest.TestCase):
    def fixture(self, folder):
        root = folder / 'ava'
        root.mkdir()
        (root / 'ava-digits-plugin.php').write_text('<?php\n/* Version: 1.1.0 */\n')
        # The version parser reads a header line, as WordPress does.
        (root / 'ava-digits-plugin.php').write_text('<?php\n/*\nVersion: 1.1.0\n*/\n')
        (root / 'LICENSE').write_text('Ava fixture license')
        (root / 'readme.txt').write_text('Ava fixture')
        (root / '.env').write_text('do-not-ship')
        (root / 'tests').mkdir()
        (root / 'tests/private-fixture.php').write_text('do-not-ship')
        archive = folder / 'base.zip'
        main = b'<?php\n/* Plugin Name: DIGITS: WordPress Mobile Number Signup and Login */\nfunction unchanged_authentication() {}\n'
        with zipfile.ZipFile(archive, 'w') as data:
            data.writestr('digits/digit.php', main)
            data.writestr('digits/custom/gateways.php', '<?php function digits_ippanel_send() {}')
            data.writestr('digits/custom/ippanel/class-ippanel.php', '<?php // unrelated provider')
            data.writestr('digits/includes/login.php', '<?php // unchanged login')
            data.writestr('digits/vendor/LICENSE', 'Original dependency notice')
        return root, archive, hashlib.sha256(archive.read_bytes()).hexdigest(), main

    def test_preserves_core_and_notices_and_replaces_only_provider(self):
        with tempfile.TemporaryDirectory() as directory:
            root, base, digest, original = self.fixture(Path(directory))
            first = full.build(base, Path(directory) / 'full.zip', root, digest)
            second = full.build(base, Path(directory) / 'again.zip', root, digest)
            self.assertEqual(first.read_bytes(), second.read_bytes())
            with zipfile.ZipFile(first) as archive:
                self.assertIsNone(archive.testzip())
                self.assertEqual(archive.read('digits/includes/login.php'), b'<?php // unchanged login')
                self.assertEqual(archive.read('digits/vendor/LICENSE'), b'Original dependency notice')
                self.assertIn(b'function unchanged_authentication() {}', archive.read('digits/digit.php'))
                self.assertNotIn('digits/custom/ippanel/class-ippanel.php', archive.namelist())
                self.assertIn('digits/custom/ava/ava-digits-plugin.php', archive.namelist())
                self.assertNotIn('digits/custom/ava/.env', archive.namelist())
                self.assertNotIn('digits/custom/ava/tests/private-fixture.php', archive.namelist())
                self.assertEqual(archive.read('digits/custom/gateways.php'), full.BOOTSTRAP.encode())

    def test_refuses_unreviewed_reference(self):
        with tempfile.TemporaryDirectory() as directory:
            root, base, digest, original = self.fixture(Path(directory))
            with self.assertRaisesRegex(ValueError, 'SHA256'):
                full.build(base, Path(directory) / 'full.zip', root, '0' * 64)

    def test_rejects_traversal_and_symlinks_and_duplicate_members(self):
        for attack in ('traversal', 'symlink', 'duplicate'):
            with self.subTest(attack=attack), tempfile.TemporaryDirectory() as directory:
                root, base, digest, original = self.fixture(Path(directory))
                with zipfile.ZipFile(base, 'a') as archive:
                    if attack == 'traversal':
                        archive.writestr('digits/../../outside.php', 'bad')
                    elif attack == 'symlink':
                        entry = zipfile.ZipInfo('digits/link.php')
                        entry.external_attr = (stat.S_IFLNK | 0o777) << 16
                        archive.writestr(entry, '../outside.php')
                    else:
                        archive.writestr('digits/digit.php', 'overwrite')
                with self.assertRaises(ValueError):
                    full.reference_files(base, hashlib.sha256(base.read_bytes()).hexdigest())


if __name__ == '__main__':
    unittest.main()
