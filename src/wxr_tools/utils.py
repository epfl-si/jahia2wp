import os


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
