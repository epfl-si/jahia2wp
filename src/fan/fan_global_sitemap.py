from utils import Utils
from anytree import Node, RenderTree

from django.core.validators import URLValidator, ValidationError


class FanGlobalSitemap:
    # the csv delimiter
    DELIMITER = ","
    ROOT_URL = "https://www.epfl.ch"

    def __init__(self, csv_file, wp_path):
        """
        Constructor.

        :param csv_file: The path of the CSV file containing all the sites
        :param wp_path: The path of the WordPress installation
        """

        self.csv_file = csv_file

        # WordPress installation path
        self.wp_path = wp_path

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

    def _parse_existing(self, csv_file):
        # Utility method for finding websites to put in the sitemap
        # from a given list
        rows = Utils.csv_filepath_to_dict(file_path=csv_file, delimiter=self.DELIMITER)

        paths = set()

        for row in rows:
            url = row["url"]
            url = url[1:]

            data = url.split("/")

            if len(data) == 1:
                paths.add("/" + data[0])
            elif len(data) >= 3:
                paths.add("/" + data[0] + "/" + data[1])

        paths = sorted(paths)

        for path in paths:
            print("https://www.epfl.ch" + path)

    def _clean(self):
        """Deletes all the content"""
        try:
            cmd = "cd {}; wp post delete " \
                  "$(wp post list --post_type='page' --post_status='all' --format=ids --path='{}') --force" \
                .format(self.wp_path, self.wp_path)

            Utils.run_command(cmd)
        except:
            # simply means there are not posts to delete
            pass

    def generate_website(self):
        """
        Generates a global sitemap.
        """
        self._clean()

        self._validate_data()

        self._insert_pages()

        self._generate_sitemap_page()

        print("Global sitemap generated successfully.")

    def _validate_data(self):
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

    def _insert_pages(self):
        """
        Insert the pages.
        """

        # sort the urls, so the parents are before their children, e.g.:
        # https://www.epfl.ch/research
        # https://www.epfl.ch/research/domains
        # https://www.epfl.ch/research/domains/enac
        urls_sorted = sorted(self.urls.keys())

        # the WordPress pages
        pages_by_path = {}

        for url in urls_sorted:
            # the path, e.g. /research or /research/domains/enac
            path = url[len(self.ROOT_URL):]

            parent_path = ""

            if "/" in path:
                parent_path = path[:path.rfind("/")]

            # the page name (slug)
            name = url[url.rfind("/") + 1:]

            title = self.urls[url]["title_en"]

            content = url

            cmd = "wp post create --post_type=page " \
                  "--post_status=publish " \
                  "--post_name='{}' " \
                  "--post_title='{}' " \
                  "--post_content='{}' " \
                  "--path='{}' " \
                  "--porcelain ".format(name, title, content, self.wp_path)

            # check if the page has a parent
            if parent_path:
                parent = pages_by_path[parent_path]

                cmd += "--post_parent={}".format(parent["id"])

            page_id = Utils.run_command(cmd)

            # add the page info
            page = {
                "path": path,
                "id": page_id
            }

            pages_by_path[path] = page


    def _generate_sitemap_page(self):
        """Generates the sitemap"""

        cmd = "wp post create --post_type=page " \
              "--post_status=publish " \
              "--post_name='sitemap' " \
              "--post_title='Sitemap' " \
              "--post_content='[simple-sitemap show_label=\"false\" types=\"page orderby=\"menu_order\"]' " \
              "--path='{}'".format(self.wp_path)

        Utils.run_command(cmd)

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
