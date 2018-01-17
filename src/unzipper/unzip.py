"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""
import os
import logging
import zipfile


def unzip_one(output_dir, site_name, zip_file):
    # create subdir in output_dir
    output_subdir = os.path.join(output_dir, site_name)
    if output_subdir:
        if not os.path.isdir(output_subdir):
            os.mkdir(output_subdir)

    # check if unzipped files already exists
    unzip_path = os.path.join(output_subdir, site_name)
    if os.path.isdir(unzip_path):
        logging.info("Already unzipped %s" % unzip_path)
        return unzip_path

    logging.info("Unzipping %s..." % zip_file)

    # make sure we have an input file
    if not zip_file or not os.path.isfile(zip_file):
        logging.error("%s - unzip - Jahia zip file %s not found", site_name, zip_file)
        raise ValueError("Jahia zip file not found")

    # create zipFile to manipulate / extract zip content
    export_zip = zipfile.ZipFile(zip_file, 'r')

    # make sure we have the zip containing the site
    zip_name = "%s.zip" % site_name
    if zip_name not in export_zip.namelist():
        logging.error("%s - unzip - zip file %s not found in main zip", site_name, zip_name)
        raise ValueError("Jahia zip file does not contain site file")

    # extract the export zip file
    export_zip.extractall(output_subdir)
    export_zip.close()

    # unzip the zip with the files
    zip_path = os.path.join(output_subdir, zip_name)
    zip_ref_with_files = zipfile.ZipFile(zip_path, 'r')
    zip_ref_with_files.extractall(unzip_path)

    logging.info("Site successfully extracted in %s" % unzip_path)
    return unzip_path
