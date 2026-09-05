import datetime
import importlib.util
from pathlib import Path
import unittest
from zoneinfo import ZoneInfo

spec = importlib.util.spec_from_file_location('monitor', Path(__file__).resolve().parents[2] / 'deploy/pi/monitor.py')
monitor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(monitor)


class MonitorTest(unittest.TestCase):
    def test_overnight_and_daytime_schedules(self):
        state = {'enabled': True, 'off': '23:00', 'on': '08:00'}
        for hour, expected in [(23, True), (2, True), (7, True), (8, False), (15, False)]:
            self.assertEqual(expected, monitor.resting(state, datetime.datetime(2026, 9, 5, hour)))
        self.assertTrue(monitor.resting({'enabled': True, 'off': '12:00', 'on': '14:00'}, datetime.datetime(2026, 9, 5, 13)))

    def test_maintenance_and_disabled_keep_screen_awake(self):
        now = datetime.datetime(2026, 9, 5, 2)
        self.assertFalse(monitor.resting({'enabled': False}, now))
        self.assertFalse(monitor.resting({'enabled': True, 'maintenance': True}, now))

    def test_both_occurrences_during_dst_fallback_use_zurich_wall_clock(self):
        state = {'enabled': True, 'off': '23:00', 'on': '08:00'}
        for utc_hour in [0, 1]:
            local = datetime.datetime(2026, 10, 25, utc_hour, 30, tzinfo=datetime.timezone.utc).astimezone(ZoneInfo('Europe/Zurich'))
            self.assertEqual(2, local.hour)
            self.assertTrue(monitor.resting(state, local))


if __name__ == '__main__':
    unittest.main()
