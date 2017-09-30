import logging

from generator.generator import WPGenerator
from utils import Utils


def test_generate():

    generator = WPGenerator(
        openshift_env=Utils.get_mandatory_env(key="WP_ENV"),
        wp_site_url="https://localhost/folder",
        wp_default_site_title="My test",
        owner_id="157489",
        responsible_id="157489")

    generator.generate()


def main():
    logging.basicConfig(level=logging.DEBUG)
    test_generate()


if __name__ == '__main__':
    main()
