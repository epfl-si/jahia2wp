import logging
import subprocess

from utils import Utils


class Generator:

    @staticmethod
    def set_environment_variables():

        return {
            'mysql_wp_user':  Utils.get_mandatory_env(key="MYSQL_WP_USER"),
            'mysql_wp_password': Utils.get_mandatory_env(key="MYSQL_WP_PASSWORD"),
            'mysql_db_host': Utils.get_mandatory_env(key="MYSQL_DB_HOST"),
            'mysql_super_user': Utils.get_mandatory_env(key="MYSQL_SUPER_USER"),
            'mysql_super_password': Utils.get_mandatory_env(key="MYSQL_SUPER_PASSWORD"),
            'wp_db_name': Utils.get_mandatory_env(key="WP_DB_NAME"),
            'wp_env': Utils.get_mandatory_env(key="WP_ENV"),
            'site_path': Utils.get_mandatory_env(key="SITE_PATH"),
            'wp_title': Utils.get_mandatory_env(key="WP_TITLE"),
            'wp_admin_user': Utils.get_mandatory_env(key="WP_ADMIN_USER"),
            'wp_admin_password': Utils.get_mandatory_env(key="WP_ADMIN_PASSWORD"),
            'wp_admin_email': Utils.get_mandatory_env(key="WP_ADMIN_EMAIL"),
        }

    @staticmethod
    def wp_cli(command, path, site):
        try:
            cmd = "wp {} --path='{}'".format(command, path)
            logging.debug("exec '%s'", cmd)
            return subprocess.check_output(cmd, shell=True)
        except subprocess.CalledProcessError as err:
            logging.error("%s - WP export - wp_cli failed : %s", site, err)
            return None

    @staticmethod
    def run_command(command, site):
        try:
            subprocess.check_output(command, shell=True)
            logging.debug("Generator - {0} - Run command {1}".format(site, command))
        except subprocess.CalledProcessError as err:
            logging.error("Generator - {0} - Command {1} failed {2}".format(site, command, err))
            return False

    @classmethod
    def run(cls, csv_file):

        sites = Utils.csv_to_dict(file_path=csv_file, delimiter=';')

        env_vars = cls.set_environment_variables()

        for site in sites:

            mysql_connection_string = "@mysql -h {mysql_db_host} -u {mysql_super_user} " \
                                    "--password={mysql_super_password}".format(**env_vars)

            # create MySQL user
            command = mysql_connection_string
            command += "-e \"CREATE USER '{mysql_wp_user}' IDENTIFIED BY '{mysql_wp_password}';\"".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            # grant privileges
            command = mysql_connection_string
            command += "-e \"GRANT ALL PRIVILEGES ON \`{wp_db_name}\`.* TO \`{mysql_wp_user}\`@'%';".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            # create htdocs path
            command = "mkdir -p /srv/{wp_env}/{site_path}/htdocs".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            # install WordPress 4.8
            command = "wp core download --version=4.8 --path=/srv/{wp_env}/{site_path}/htdocs".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            # config WordPress
            command = "wp config create --dbname={wp_db_name} --dbuser={mysql_wp_user}".format(**env_vars)
            command += " --dbpass={mysql_wp_password} --dbhost={mysql_db_host}".format(**env_vars)
            command += " --path=/srv/{wp_env}/{site_path}/htdocs".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            #
            command = "wp db create --path=/srv/{wp_env}/{site_path}/htdocs".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])

            command = "wp --allow-root core install --url=http://{site_path} --title={wp_title}".format(**env_vars)
            command += " --admin_user={wp_admin_user} --admin_password={wp_admin_password}".format(**env_vars)
            command += " --admin_email={wp_admin_email} --path=/srv/{wp_env}/{site_path}/htdocs".format(**env_vars)
            cls.run_command(command, site=site["wp_site_title"])
