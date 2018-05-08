from utils import Utils
from anytree import Node, RenderTree

from django.core.validators import URLValidator, ValidationError


class FanGlobalSitemap:
    # the csv delimiter
    DELIMITER = ","
    ROOT_URL = "https://www.epfl.ch"

    def __init__(self, csv_file):

        self.csv_file = csv_file

        # WordPress installation path
        self.wp_path = "."

        # the rows
        self.rows = []

        # the errors
        self.errors = []

        # load the rows
        self.rows = Utils.csv_filepath_to_dict(file_path=self.csv_file, delimiter=self.DELIMITER)

        # the urls, key is the URL, value is another dict with all the data
        # (titles, UNIT, etc.)
        self.urls = {}

        for row in self.rows:
            url = row["url"]
            self.urls[url] = row

    def clean(self):
        """Deletes all the content"""
        try:
            cmd = "cd {}; wp post delete " \
                  "$(wp post list --post_type='page' --post_status='all' --format=ids --path='{}') --force" \
                .format(self.wp_path, self.wp_path)

            Utils.run_command(cmd)
        except:
            # simply means there are not posts to delete
            pass

    def generate_global_sitemap(self):
        """
        Generates a global sitemap.
        """
        self.validate_data()

        self.insert_pages()

    def validate_data(self):
        """
        Validates the data.
        """

        # line
        i = 0

        for row in self.rows:
            i = i + 1

            url = row["url"]

            # valid URL
            try:
                url_validator = URLValidator()
                url_validator(url)
            except ValidationError:
                self._add_error(i, "URL '{}' is invalid".format(url))
                continue

            # start with the ROOT_PATH
            if not url.startswith(self.ROOT_URL):
                self._add_error(i, "URL '{}' must start with {}".format(url, self.ROOT_URL))
                continue

            parent_url = url[:url.rfind("/")]

            if parent_url != self.ROOT_URL and parent_url not in self.urls:
                self._add_error(i, "URL '{}' doesn't have a parent".format(url))

        if self.errors:
            print("The CSV contains errors, please correct them:\n")
            for error in self.errors:
                print(error)
            exit()

    def insert_pages(self):
        """
        Insert the pages.
        """

        # sort the urls, so the hierarchy is correct, e.g.
        # https://www.epfl.ch/research
        # https://www.epfl.ch/research/domains
        # https://www.epfl.ch/research/domains/enac
        urls_sorted = sorted(self.urls.keys())

        # the WordPress pages
        pages_by_slug = {}

        for url in urls_sorted:
            slug = url[len(self.ROOT_URL) + 1:]

            parent_slug = ""

            if "/" in slug:
                parent_slug = slug[:slug.rfind("/")]

            title = self.urls[url]["title_en"]

            cmd = "wp post create --post_type=page " \
                  "--post_status=publish " \
                  "--post_name='{}' " \
                  "--post_title='{}' " \
                  "--path='{}' " \
                  "--porcelain ".format(slug, title, self.wp_path)

            page_id = Utils.run_command(cmd)

            # check if the page has a parent
            if parent_slug:
                parent = pages_by_slug[parent_slug]

                cmd += "--post-parent={}".format(parent["id"])

            # add the page info
            page = {
                "slug": slug,
                "id": page_id
            }

            pages_by_slug[slug] = page

    def _add_error(self, line, message):
        """
        Add an error.

        :param line: the line number.
        :param message: the error message
        """
        self.errors.append("Line {}: {}".format(line, message))


class TreeNode(Node):

    def __init__(self, path, parent=None):
        super().__init__(path, parent)

    pass

    def print_node(self):
        """Print the node"""

        for pre, fill, node in RenderTree(self):
            print("%s%s" % (pre, node.name))
