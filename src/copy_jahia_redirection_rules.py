from ops import SshRemoteSite

url = "https://dcsl.epfl.ch"

site = SshRemoteSite(url)

content = site.get_htaccess_content()
content = content.decode("utf-8")

print(content)