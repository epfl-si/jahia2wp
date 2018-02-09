import datetime
import pytest
import uuid
import logging
import os
from importlib import reload
import settings

import requests
from bs4 import BeautifulSoup

from settings import OPENSHIFT_ENV
from wordpress.generator import MockedWPGenerator
from utils import Utils

# This Docker IP address is used for automatic testing.
# Docker may change it in the future, which will cause some tests to fail.
HTTPD_CONTAINER = Utils.get_optional_env("HTTPD_CONTAINER", "httpd")

"""
Load fake environment variables for every test
FIXME: Can be improved. Information can be found here :
- https://docs.pytest.org/en/2.7.3/xunit_setup.html
- http://agiletesting.blogspot.ch/2005/01/python-unit-testing-part-3-pytest-tool.html
"""
os.environ["PLUGINS_CONFIG_BASE_PATH"] = "wordpress/tests/plugins"
reload(settings)

class TestWpUploadTest:

    SAME_SCIPER_ID = "188475"

    @pytest.fixture()
    def session(self):
        logging.debug("Starting new session")
        session = requests.session()
        # TODO close the session properly
        return session

    @pytest.fixture()
    def wp_generator(self):
        generator = MockedWPGenerator(
            {'openshift_env': OPENSHIFT_ENV,
             'wp_site_url': "https://" + HTTPD_CONTAINER + "/folder",
             'wp_site_title': "UP",
             'wp_tagline': "upload test"},
            admin_password="admin")
        generator.clean()
        generator.generate()
        command = "option add plugin:epfl_tequila:has_dual_auth 1"
        if not generator.run_wp_cli(command):
            raise ValueError("Could not set option on generated site")
        return generator

    def test_upload_image_to_wordpress(self, wp_generator, session):

        username = wp_generator.wp_admin.username

        lcbase_path = "https://" + HTTPD_CONTAINER + ":8443/folder"

        # login to WordPress
        logging.debug("Login in to WordPress")
        link = "{base_path}/wp-login.php".format(base_path=lcbase_path)

        login_data = {"log": username,
                      "pwd": wp_generator.wp_admin.password,
                      "rememberme": "forever",
                      "redirect_to": "{base_path}/wp-admin".format(base_path=lcbase_path),
                      "redirect_to_automatic": "1"
                      }
        # 'verify=False' is to ignore SSL certificate error because we are accessing using HTTPS but without
        # any valid certificate \o/
        page_login = session.post(link, login_data, verify=False)
        assert page_login.status_code is 200

        # check that the user is correctly logged in (i.e. his name shows up correctly on the page
        logging.debug("Checking that the user is correctly logged in")
        soup = BeautifulSoup(page_login.content, "lxml")
        logged_in_user_element = soup.find('span', {'class': 'display-name'})
        logged_in_user_display_name = logged_in_user_element.text if logged_in_user_element else ''
        assert logged_in_user_display_name == username

        # go the media upload page (in order to generate a nonce)
        logging.debug("Getting the media upload page in order to get wp_nonce")
        upload_page = session.get('{base_path}/wp-admin/media-new.php'.format(base_path=lcbase_path))

        # Grab the _wp_nonce
        logging.debug("Getting the wp_nonce")
        soup = BeautifulSoup(upload_page.content, 'lxml')
        wp_nonce_element = soup.find('input', {'name': '_wpnonce'})
        wp_nonce = wp_nonce_element.get('value')

        # generates a unique file name in order to make sure that there is no collision while uploading
        logging.debug("Generating a unique file name")
        unique_file_name = str(uuid.uuid1()) + '.jpg'

        # upload the image
        logging.debug("Uploading the image")

        os.getenv("SUT_PASSWORD", 'admin')

        file_path = os.path.join(os.path.dirname(os.path.realpath(__file__)), 'media.jpg')

        with open(file_path, 'rb') as f:
            upload_data = {'post_id': '0',
                           '_wp_http_referer': '/wp-admin/media-new.php',
                           '_wpnonce': wp_nonce,
                           'action': 'upload_attachement',
                           'html-upload': 'Upload'}
            files = {'async-upload': (unique_file_name, f)}
            upload_result = session.post(
                '{base_path}/wp-admin/media-new.php'.format(base_path=lcbase_path),
                data=upload_data,
                files=files
            )
        assert upload_result.status_code is 200

        # checks if the image has really been uploaded
        logging.debug("Cross cjecking that the image was really uploaded")
        image_url = "{base_path}/wp-content/uploads/{timestamp:%Y/%m}/{filename}".format(
            base_path=lcbase_path,
            timestamp=datetime.date.today(),
            filename=unique_file_name)
        print(image_url)
        image_page = session.get(image_url)
        assert image_page.status_code is 200

        # cleanup up the mess
        logging.debug("Starting cleanup the uploaded file")
        upload_management_page_url = "{base_path}/wp-admin/upload.php?mode=list".format(
            base_path=lcbase_path)
        upload_management_page = session.get(upload_management_page_url)
        assert upload_management_page.status_code is 200
        soup = BeautifulSoup(upload_management_page.content, 'lxml')

        # get list of filenames
        logging.debug("Getting the list of filenames")
        filename_elements = soup.find_all('p', {'class': 'filename'})
        assert len(filename_elements) > 0
        for filename_element in filename_elements:
            if unique_file_name in filename_element.text:
                table_cell_element = filename_element.parent
                delete_link = table_cell_element.find('a', {'class': 'submitdelete'})
                delete_link = delete_link['href']
                delete_link = "{base_path}/wp-admin/{link}".format(base_path=lcbase_path, link=delete_link)

                logging.debug("Deleting the file")
                delete_media_result = session.get(delete_link)
                assert delete_media_result.status_code is 200

        # checks if the image has really been cleaned
        logging.debug("Cross checking that the file has been correctly deleted")
        image_page = session.get(image_url)
        assert image_page.status_code == 404

        # clean WP site
        wp_generator.clean()
