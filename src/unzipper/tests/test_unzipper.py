import os
# import shutil

from unzipper.unzip import unzip_one


class TestUnzipper(object):

    CURRENT_DIR = os.path.dirname(__file__)
    TEST_FILE = os.path.join(CURRENT_DIR, "one-site_export_2017-10-11-05-03.zip")
    TEST_JAHIA_SITE = "one-site"

    def test_unzipped_files_existing(self):

        unzip_path = unzip_one(
            output_dir=self.CURRENT_DIR,
            site_name=self.TEST_JAHIA_SITE,
            zip_file=self.TEST_FILE
        )

        TEST_JAHIA_SITE_PATH = os.path.join(self.CURRENT_DIR, self.TEST_JAHIA_SITE)
        unzip_path_expected = os.path.join(TEST_JAHIA_SITE_PATH, self.TEST_JAHIA_SITE)

        assert unzip_path == unzip_path_expected

        # clean
        # shutil.rmtree(TEST_JAHIA_SITE_PATH)
