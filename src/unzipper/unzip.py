"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import logging
import zipfile
import sys


def unzip_one(output_dir, site_name, zip_file):
    """
    Unzip a jahia zip file

    A jahia zip file contains many files including a zip file <site-name>.zip.
    This file will be unzipped too and the path of the unzipped root directory
    will be return.

    :param output_dir: directory where the jahia zip file will be unzipped
    :param site_name: WordPress name site
    :param zip_file: name of jahia zip file
    """

    # create subdir in output_dir
    output_subdir = os.path.join(output_dir, site_name)
    if output_subdir:
        if not os.path.isdir(output_subdir):
            os.mkdir(output_subdir)

    # check if unzipped files already exists
    unzip_path = os.path.join(output_subdir, site_name)
    if os.path.isdir(unzip_path):
        logging.info("Already unzipped %s", unzip_path)
        return unzip_path
    else:
        os.makedirs(unzip_path)

    logging.info("Unzipping %s...", zip_file)

    # make sure we have an input file
    if not zip_file or not os.path.isfile(zip_file):
        logging.error("%s - unzip - Jahia zip file %s not found", site_name, zip_file)
        raise ValueError("Jahia zip file not found")

    # create zipFile to manipulate / extract zip content
    export_zip = zipfile.ZipFile(zip_file, 'r')

    # make sure we have the zip containing the site
    zip_name = "{}.zip".format(site_name)
    if zip_name not in export_zip.namelist():
        logging.error("%s - unzip - zip file %s not found in main zip", site_name, zip_name)
        raise ValueError("Jahia zip file does not contain site file")

    # extract the export zip file
    export_zip.extractall(output_subdir)
    export_zip.close()

    # unzip the zip with the files
    zip_path = os.path.join(output_subdir, zip_name)
    zip_ref_with_files = zipfile.ZipFile(zip_path, 'r')
    #zip_ref_with_files.extractall(unzip_path)
    for m in zip_ref_with_files.infolist():
        data = zip_ref_with_files.read(m) # extract zipped data into memory
        # convert unicode file path to utf8
        disk_file_name = m.filename.encode('utf8')
        dir_name = os.path.dirname(disk_file_name)
        logging.info(disk_file_name)
        logging.info(dir_name)
        if dir_name:
            try:
                os.makedirs(os.path.join(unzip_path.encode('utf-8'), dir_name))
            except OSError as e:
                if e.errno == os.errno.EEXIST:
                    pass
                else:
                    raise
            except Exception as e:
                raise

        with open(os.path.join(unzip_path.encode('utf-8'), disk_file_name), 'wb') as fd:
            fd.write(data)
    zip_ref_with_files.close()

    logging.info("Site successfully extracted in %s", unzip_path)
    return unzip_path
