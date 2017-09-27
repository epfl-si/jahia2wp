from generator.generator import WPGenerator


def test_generate():

    generator = WPGenerator(openshift_env="dev",
                            wp_site_url="https://localhost/folder",
                            wp_default_site_title="My test",
                            owner_id="157489",
                            responsible_id="157489")

    generator.generate()
