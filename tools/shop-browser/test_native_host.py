import io
import json
import struct
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch
import native_host


class NativeHostTests(unittest.TestCase):
    def test_identity_strips_challenge_and_preserves_store(self):
        self.assertEqual(native_host.identity('https://www.suruga-ya.jp/product/detail/ABC?tenpo_cd=12&__cf_chl_rt_tk=secret'),
                         ('suruga', 'https://www.suruga-ya.jp/product/detail/ABC?tenpo_cd=12'))

    def test_rejects_unrelated_destinations(self):
        for url in ('http://www.suruga-ya.jp/product/detail/ABC', 'https://evil.test/product/detail/ABC',
                    'https://u:p@www.suruga-ya.jp/product/detail/ABC', 'https://www.suruga-ya.jp/login'):
            with self.assertRaises(ValueError): native_host.identity(url)

    def test_message_filters_top_level_fields(self):
        payload = json.dumps({'url':'https://www.melonbooks.co.jp/detail/detail.php?product_id=12&token=secret',
                              'prices':[], 'cookies':'must-not-save'}).encode()
        stdin = type('Input', (), {'buffer':io.BytesIO(struct.pack('<I',len(payload))+payload)})()
        stdout = type('Output', (), {'buffer':io.BytesIO()})()
        with tempfile.TemporaryDirectory() as directory, patch.object(native_host, 'ROOT', Path(directory)), \
                patch.object(native_host.sys,'stdin',stdin), patch.object(native_host.sys,'stdout',stdout):
            native_host.main()
            saved = json.loads((Path(directory)/'dom-melon.json').read_text())
        self.assertNotIn('cookies', saved)
        self.assertNotIn('token', saved['url'])
        self.assertEqual(json.loads(stdout.buffer.getvalue()[4:]), {'ok':True})


if __name__ == '__main__': unittest.main()
