"""(c) All rights reserved. ECOLE POLYTECHNIQUE FEDERALE DE LAUSANNE, Switzerland, VPSI, 2018"""


from bs4 import BeautifulSoup
import re

class Banner:
    """ To store website banner information. """

    # FIXME: extend class with more information if html content is not enough to handle banner
    def __init__(self, content):
        """ Constructor

        content - HTML content of the banner """

        # If there are image in banner, they may have following code aspect, so cleaning is necessary :
        # ###file:/content/sites/skyrmions/files/Image-1.jpg?uuid=default:d1c1c1d4-7d23-45d7-b6fc-c10df12ef91e
        soup = BeautifulSoup(content, 'html.parser')

        images = soup.find_all('img')

        for image in images:
            # Cleaning image source
            # FIXME: Maybe there's a better way to remove the /content/sites/<sitename> from URL...
            image['src'] = re.sub(r"###file:/content/sites/[a-zA-Z0-9-\.]+|\?.+", "", image.get('src'))

        self.content = str(soup)