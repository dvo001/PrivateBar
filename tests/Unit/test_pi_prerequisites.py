"""Read-only tests: no root access, package installation or host changes."""
import pathlib
import subprocess
import unittest

SCRIPT = pathlib.Path(__file__).resolve().parents[2] / 'deploy/pi/install-prerequisites.sh'


class PrerequisitesTest(unittest.TestCase):
    def shell(self, body, *arguments):
        return subprocess.run(
            ['bash', '-c', 'source "$1"; shift; ' + body, 'test', str(SCRIPT), *arguments],
            capture_output=True, text=True, check=False,
        )

    def test_supported_pi_platforms(self):
        for codename in ('bookworm', 'trixie'):
            with self.subTest(codename=codename):
                result = self.shell('validate_platform "$@"', 'debian', codename, 'arm64', 'Raspberry Pi 4 Model B')
                self.assertEqual(result.returncode, 0, result.stderr)

    def test_unsupported_platforms_fail(self):
        cases = [
            ('ubuntu', 'trixie', 'arm64', 'Raspberry Pi 4'),
            ('debian', 'bullseye', 'arm64', 'Raspberry Pi 4'),
            ('debian', 'trixie', 'armhf', 'Raspberry Pi 4'),
            ('debian', 'trixie', 'amd64', 'Raspberry Pi 4'),
            ('debian', 'trixie', 'arm64', 'Other board'),
        ]
        for arguments in cases:
            with self.subTest(arguments=arguments):
                self.assertNotEqual(self.shell('validate_platform "$@"', *arguments).returncode, 0)

    def test_package_list_pins_php_and_includes_pi_components(self):
        result = self.shell('packages')
        self.assertEqual(result.returncode, 0)
        packages = set(result.stdout.splitlines())
        self.assertTrue({'php8.3-cli', 'php8.3-fpm', 'php8.3-gd', 'php8.3-mysql',
                         'mariadb-server', 'nginx', 'chromium', 'python3',
                         'cifs-utils', 'wlr-randr'}.issubset(packages))
        self.assertNotIn('php', packages)
        self.assertNotIn('php-fpm', packages)

    def test_missing_and_present_candidates(self):
        for candidate, expected in [('(none)', 1), ('', 1), ('8.3.30-1', 0)]:
            result = self.shell('apt-cache() { printf "  Candidate: %s\\n" "$mock_candidate"; }; mock_candidate=$1; has_php_candidate php8.3-cli', candidate)
            self.assertEqual(result.returncode, expected, result.stderr)

    def test_apt_cache_failure_is_not_a_valid_candidate(self):
        result = self.shell('apt-cache() { return 42; }; has_php_candidate php8.3-cli')
        self.assertNotEqual(result.returncode, 0)

    def test_help_does_not_invoke_package_manager(self):
        result = self.shell('apt-get() { echo UNEXPECTED; return 99; }; main --help')
        self.assertEqual(result.returncode, 0)
        self.assertNotIn('UNEXPECTED', result.stdout)
        self.assertIn('--install', result.stdout)

    def test_unknown_option_is_rejected_before_system_changes(self):
        result = self.shell('apt-get() { echo UNEXPECTED; return 99; }; main --wrong')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('UNEXPECTED', result.stdout)


if __name__ == '__main__':
    unittest.main()
