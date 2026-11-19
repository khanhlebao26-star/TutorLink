import unittest
from dashboard import bytes_value, threshold_alerts

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

if __name__=='__main__': unittest.main()
