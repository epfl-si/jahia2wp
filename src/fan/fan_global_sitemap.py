import settings
from utils import Utils
from anytree import Node

from django.core.validators import URLValidator, ValidationError


class FanGlobalSitemap:
    # the csv delimiter
    DELIMITER = ","
    # the root url
    ROOT_URL = "https://www.epfl.ch"
    # the title (blogname)
    WEBSITE_TITLE = "Global sitemap"
    # if True create a homepage that is the parent of all the pages
    CREATE_HOMEPAGE = False
    # the root path of the WP site. For example if the global sitemap is deployed
    # on https://jahia2wp-httpd/www.epfl.ch the root path is /www.epfl.ch. Having the
    # root path as /www.epfl.ch allows us to simulate the final URLs, e.g.
    # https://jahia2wp-httpd/www.epfl.ch/faculty/ic
    WP_ROOT_PATH = "/www.epfl.ch"

    def __init__(self, csv_file, wp_path):
        """
        Constructor.

        :param csv_file: The path of the CSV file containing all the sites
        :param wp_path: The path of the WordPress installation
        """

        # save the attributes
        self.csv_file = csv_file
        self.wp_path = wp_path

        # the CSV rows
        self.rows = []

        # the validation errors
        self.errors = []

        # load the rows
        self.rows = Utils.csv_filepath_to_dict(file_path=self.csv_file, delimiter=self.DELIMITER)

        # the urls as a dict, key is the URL, value is another dict with all the metadata
        # (titles, UNIT, etc.)
        self.urls = {}

        for row in self.rows:
            url = row["wp_site_url"]
            self.urls[url] = row

    def create_website(self):
        """
        Creates the website with the global sitemap.
        """
        self._validate_data()

        self._clean()

        self._create_menus()

        self._create_pages()

        self._set_options()

        print("Global sitemap generated successfully.")

    def _validate_data(self):
        """
        Validates the CSV data.
        """

        # line number
        i = 0

        for row in self.rows:
            i = i + 1

            url = row["wp_site_url"]

            # the URL must...

            # be well formed
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

            # have a parent
            parent_url = url[:url.rfind("/")]

            if parent_url != self.ROOT_URL and parent_url not in self.urls:
                self._add_error(i, "URL '{}' doesn't have a parent".format(url))

        # if there are errors we print them and stop
        if self.errors:
            print("The CSV contains errors, please correct them:\n")
            for error in self.errors:
                print(error)
            exit()

    def _clean(self):
        """Deletes all the site content"""

        # delete the pages
        try:
            cmd = "cd {}; wp post delete " \
                  "$(wp post list --post_type='page' --post_status='all' --format=ids --path='{}') --force" \
                .format(self.wp_path, self.wp_path)

            Utils.run_command(cmd)
        except:
            # simply means there are not posts to delete
            pass

        # delete the menus
        try:
            cmd = "cd {}; wp menu delete " \
                  "$(wp menu list --format=ids)".format(self.wp_path)

            Utils.run_command(cmd)
        except:
            # simply means there are not menus to delete
            pass

    def _create_menus(self):
        """Creates the menus"""

        self._create_menu(settings.MAIN_MENU)
        self._create_menu(settings.FOOTER_MENU)

    def _create_menu(self, name):
        """Creates a menu with the given name"""

        cmd = "wp menu create {} --path='{}' --porcelain"
        cmd = cmd.format(name, self.wp_path)

        return Utils.run_command(cmd)

    def _add_to_menu(self, menu_name, page_id, menu_item_parent_id=None):
        """Adds the given page to the given menu"""

        cmd = "wp menu item add-post {} {} --path='{}' --porcelain"

        # set the parent menu item, if any
        if menu_item_parent_id:
            cmd += " --parent-id={}".format(menu_item_parent_id)

        cmd = cmd.format(menu_name, page_id, self.wp_path)

        return Utils.run_command(cmd)

    def _create_pages(self):
        """
        Creates the pages.
        """

        # sort the urls so the parents are before their children, e.g.:
        # https://www.epfl.ch/research
        # https://www.epfl.ch/research/domains
        # https://www.epfl.ch/research/domains/enac
        urls_sorted = sorted(self.urls.keys())

        # first insert the homepage
        homepage_id = None

        if self.CREATE_HOMEPAGE:
            homepage_id = self._create_homepage()

        # the WordPress pages
        pages_by_path = {}

        # the nodes, this is used to create the static sitemap
        nodes_by_path = {}

        # add the homepage
        homepage_node = GlobalSitemapNode("/", "Home")
        nodes_by_path["/"] = homepage_node

        # first we add all the nodes, we will need the complete sitemap later
        for url in urls_sorted:
            # the path, e.g. /research or /research/domains/enac
            path = url[len(self.ROOT_URL):]

            parent_path = path[:path.rfind("/")]

            if not parent_path:
                parent_path = "/"

            parent_node = nodes_by_path[parent_path]

            title = self.urls[url]["wp_site_title"]

            node = GlobalSitemapNode(path, title, parent_node)

            nodes_by_path[path] = node

        # next we create the pages
        for url in urls_sorted:
            # the path, e.g. /research or /research/domains/enac
            path = url[len(self.ROOT_URL):]

            parent_path = path[:path.rfind("/")]

            # the page name (slug)
            name = url[url.rfind("/") + 1:]

            title = self.urls[url]["wp_site_title"]

            content = nodes_by_path[path].html()

            parent_id = homepage_id
            menu_item_parent_id = None

            # check if the page has a parent
            if parent_path:
                parent_id = pages_by_path[parent_path]["id"]
                menu_item_parent_id = pages_by_path[parent_path]["menu_item_id"]

            page_id = self._create_page(name, title, content, parent_id)
            menu_item_id = self._add_to_menu(settings.MAIN_MENU, page_id, menu_item_parent_id)

            # add the page info
            page = {
                "id": page_id,
                "parent_id": parent_id,
                "menu_item_id": menu_item_id,
                "path": path,
            }

            pages_by_path[path] = page

        # sitemap
        self._create_sitemap(homepage_id)

    def _create_homepage(self):
        """Creates the homepage"""

        homepage_id = self._create_page("home", "Home", "Home page")

        self._add_to_menu(settings.MAIN_MENU, homepage_id)

        return homepage_id

    def _create_sitemap(self, homepage_id):
        """Creates the sitemap page"""

        content = '[simple-sitemap show_label="false" types="page orderby="menu_order"]'

        page_id = self._create_page("sitemap", "Sitemap", content, homepage_id)

        self._add_to_menu(settings.FOOTER_MENU, page_id)

        # set the sitemap as the homepage
        cmd = "wp option update show_on_front page --path='{}'".format(self.wp_path)
        Utils.run_command(cmd)

        cmd = "wp option update page_on_front {} --path='{}'".format(page_id, self.wp_path)
        Utils.run_command(cmd)

    def _set_options(self):
        """Sets the website options"""

        # title
        cmd = "wp option update blogname '{}' --path='{}'".format(self.WEBSITE_TITLE, self.wp_path)
        Utils.run_command(cmd)

        # tagline
        cmd = "wp option update blogdescription '{}' --path='{}'".format("", self.wp_path)
        Utils.run_command(cmd)

    def _create_page(self, name, title, content, parent_id=None):
        """Creates a page with the given informations"""

        cmd = "wp post create --post_type=page " \
              "--post_status=publish " \
              "--post_name='{}' " \
              "--post_title='{}' " \
              "--post_content='{}' " \
              "--path='{}' " \
              "--porcelain ".format(name, title, content, self.wp_path)

        if parent_id:
            cmd += "--post_parent={}".format(parent_id)

        return Utils.run_command(cmd)

    def _add_error(self, line, message):
        """
        Adds an error found in the CSV file.

        :param line: the line number
        :param message: the error message
        """
        self.errors.append("Line {}: {}".format(line, message))


class GlobalSitemapNode(Node):
    """
    This class allows us to represent the sitemap as a Tree, using the
    anytree library.
    """

    def __init__(self, path, title, parent_path=None):
        super().__init__(path, parent_path)

        self.title = title

    def html(self):
        """Returns the node html"""

        html = ""

        # the ancestors
        for node in self.ancestors:
            full_path = FanGlobalSitemap.WP_ROOT_PATH + node.name

            node_html = "<ul>\n"
            node_html += "  <li><a href='{}'>{}</a>\n"

            html += node_html.format(full_path, node.title)

        # the node itself
        html += "<ul><li><strong>{}</strong>".format(self.title)

        # it's children, if any
        if self.children:
            html += "<ul>"
            for child in self.children:
                html += self._li_html(child.name, child.title) + "</li>"
            html += "</ul>"

        # close the node
        html += "</li></ul>"

        # close the ancestors
        for _ in self.ancestors:
            html += "</li>"
            html += "</ul>"

        return html

    def _li_html(self, path, name):
        """Returns the html for a <li> of the given path"""

        return "<li><a href='{}'>{}".format(FanGlobalSitemap.WP_ROOT_PATH + path, name)
