import logging
import subprocess

from utils import Utils


class Generator:

    @staticmethod
    def wp_cli(command, path, site):
        try:
            cmd = "wp {} --path='{}'".format(command, path)
            logging.debug("exec '%s'", cmd)
            return subprocess.check_output(cmd, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error("%s - WP export - wp_cli failed : %s", site, err)
            return None

    @classmethod
    def run(cls, csv_file):

        sites = Utils.csv_to_dict(file_path=csv_file)

        for site in sites:
            command = "core download --version=4.8"
            env_wp_path = Utils.get_mandatory_env(key="WP_PATH")
            path = env_wp_path + "/htdocs"
            cls.wp_cli(command, path, site=site["wp_site_title"])
