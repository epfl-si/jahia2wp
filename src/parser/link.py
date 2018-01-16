"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2017"""


class Link:
    """A link"""

    def __init__(self, url, title):
        self.title = title
        self.url = url

    def __str__(self):
        return "<a href='%s'>%s</a>" % (self.url, self.title)
