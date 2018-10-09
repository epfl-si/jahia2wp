import os
from http import HTTPStatus

import requests


def get_wp_site_url(wp_url):
    """
    Return homepage URL of WP web site.
    
    Check if URL is the 'homepage' of WP web site.
    If it's not, look for homepage by going up in the URL.
    
    :param wp_url: WP page URL  
    
    :return: WP site URL
    """
    url_tmp = os.path.split(wp_url)[0]
    response = requests.get(url_tmp + "/wp-admin")

    while response.status_code != HTTPStatus.OK:
        url_tmp = os.path.split(url_tmp)[0]
        response = requests.get(url_tmp + "/wp-admin")

    if not url_tmp.endswith("/"):
        url_tmp += "/"

    return url_tmp


def increment_xml_file_path(xml_file_path):
    """
    Return the next incremental name xml file.
    
    Example: 
    input: xml_file_path: help-actu_1.xml
    output: help-actu_2.xml
    
    :param xml_file_path: path of xml file
    
    :return: next incremental name xml name 
    """
    index = 1
    path = xml_file_path.replace(".xml", "") + "_{}.xml"
    while os.path.exists(path.format(index)):
        index += 1
    return path.format(index)
