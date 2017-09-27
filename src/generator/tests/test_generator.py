from generator.generator import WPGenerator


def test_generate():

    generator = WPGenerator(environment="dev",
                            domain="localhost",
                            folder="test",
                            title="My test",
                            webmaster="157489",
                            responsible="157489")

    generator.generate()
