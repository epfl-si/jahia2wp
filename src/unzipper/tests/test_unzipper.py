import os
import shutil

from unzipper.unzip import unzip_one


class TestUnzipper(object):

    OUTPUT_DIR = "/tmp"
    CURRENT_DIR = os.path.dirname(__file__)
    TEST_FILE = os.path.join(CURRENT_DIR, "one-site_export_2017-10-11-05-03.zip")
    TEST_JAHIA_SITE = "one-site"
    TEST_EXTRACTED_FILE = "/tmp/one-site/one-site/subfolder/test_file_in_subfolder_2018_02_15_16_32.txt"

    def test_unzipped_files_existing(self):

        unzip_path = unzip_one(
            output_dir=self.OUTPUT_DIR,
            site_name=self.TEST_JAHIA_SITE,
            zip_file=self.TEST_FILE
        )

        TEST_JAHIA_SITE_PATH = os.path.join(self.OUTPUT_DIR, self.TEST_JAHIA_SITE)
        unzip_path_expected = os.path.join(TEST_JAHIA_SITE_PATH, self.TEST_JAHIA_SITE)

        assert unzip_path == unzip_path_expected

        # test if the file on-site.zip exists
        assert os.path.isfile(unzip_path + ".zip")

        # test if the directory on-site/ exists
        assert os.path.isdir(unzip_path)

        assert os.path.isfile(self.TEST_EXTRACTED_FILE)

        # some cleaning
        shutil.rmtree(TEST_JAHIA_SITE_PATH)
