from urllib.parse import urlsplit

import os

WP_REDIRECTS_AFTER_VENTILATION = "WordPress-Redirects-After-Ventilation"


def insert_redirects_after_ventilation_in_htaccess(site_root_path, insertion, after_this_marker=None):
    """
    Insert redirects rules in htacess after ventilation

    :param site_root_path: site root path
    :param insertion: list of htaccess lines
    :param after_this_marker: insert htaccess lines after the end marker 'after_this_marker'
    """

    full_path = os.path.join(site_root_path, ".htaccess")

    if not os.path.isfile(full_path):
        if not os.access(site_root_path, os.W_OK):
            raise Exception("Path not writable: {}".format(site_root_path))
        # Creating empty file
        open(full_path, 'a').close()
    else:
        if not os.access(full_path, os.W_OK):
            raise Exception("File not writable: {}".format(full_path))

    end_marker = "# END {}".format(after_this_marker)

    with open(full_path, 'r+') as fp:

        lines = fp.readlines()

        # Remove \r\n
        lines = [line.rstrip("\r\n") for line in lines]

        wp_redirect_after_ventilation_already_exist = False

        content = []

        for line in lines:
            if line.find("# BEGIN {}".format(WP_REDIRECTS_AFTER_VENTILATION)) != -1:
                wp_redirect_after_ventilation_already_exist = True
                break
            elif line.find(end_marker) != -1:
                content.append(line)
                content += insertion
            else:
                content.append(line)

        if not wp_redirect_after_ventilation_already_exist:
            new_file_data = "\n".join(content)
            fp.seek(0)
            fp.write(new_file_data)
            fp.truncate(fp.tell())
            fp.flush()


def generate_htaccess_content(source_url, destination_url):
    """
    Generate a list of htaccess rules

    :param source_url: source URL
    :param destination_url: destiantion URL
    """

    # Delete protocol and ://
    protocol = urlsplit(source_url).scheme + "://"
    if source_url.startswith(protocol):
        source_url = source_url.replace(protocol, "")

    # Delete the trailing slash
    source_url = source_url.strip("/")

    # Destination must ending with slash
    if not destination_url.endswith("/"):
        destination_url = destination_url + "/"

    # build htaccess rules
    content = [
        "",  # a white line to breathe
        "# BEGIN {}".format(WP_REDIRECTS_AFTER_VENTILATION),
        "RewriteCond %{{HTTP_HOST}} ^{}$ [NC]".format(source_url),
        "RewriteRule ^(.*)$ {}$1 [L,QSA,R=301]".format(destination_url),
        "# END {}".format(WP_REDIRECTS_AFTER_VENTILATION)
    ]

    return content
