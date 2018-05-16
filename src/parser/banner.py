"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""


class Banner:
    """ To store website banner information. """

    # FIXME: extend class with more information if html content is not enough to handle banner
    def __init__(self, content):
        """ Constructor

        content - HTML content of the banner """
        self.content = content
