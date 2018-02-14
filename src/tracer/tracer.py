from datetime import datetime

import os

import settings


class Tracer:

    TRACER_FILE_NAME = "tracer_.csv"

    @classmethod
    def get_tracer_path(cls):
        return os.path.join(settings.JAHIA_ZIP_PATH, cls.TRACER_FILE_NAME)

    @staticmethod
    def write_row(cls, site, step, status):

        with open(cls.get_tracer_path(cls), 'a', newline='\n') as tracer:
            tracer.write(
                "{}, {}, {}, {}\n".format(
                    '{0:%Y-%m-%d %H:%M:%S}'.format(datetime.now()),
                    site,
                    step,
                    status
                )
            )
            tracer.flush()
