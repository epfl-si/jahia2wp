import os
import requests


def get_wp_site_url(wp_url):

    url_tmp = os.path.split(wp_url)[0]
    response = requests.get(url_tmp + "/wp-admin")

    while (response.status_code != 200):
        url_tmp = os.path.split(url_tmp)[0]
        response = requests.get(url_tmp + "/wp-admin")

    if not url_tmp.endswith("/"):
        url_tmp += "/"

    return url_tmp
