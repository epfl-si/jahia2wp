from jahia2wp import main


def test_main():
    assert main({'helloworld': True})
    assert not main({})
