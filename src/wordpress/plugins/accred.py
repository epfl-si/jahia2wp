import logging
import json
from .config import WPPluginConfig
from .models import WPException


class WPAccredConfig(WPPluginConfig):

    def _option_exists(self, option_name):
        """
        Tells if an option exists.
        Note: We don't use "wp option get" because even if it returns an empty string if option doesn't exists,
              it also returns 1 as exit code so it generates an error.

        Arguments keyword
        option_name -- Name of the option we are looking for.
        """
        cmd = "option list --search={} --format=json".format(option_name)

        result = self.run_wp_cli(cmd)

        if not result:
            raise WPException("Error defining if option exists '%s'".format(option_name))

        return json.loads(result) is not False

    def configure(self, force, unit_name=None, unit_id=None, **kwargs):
        """
        plugin:epfl_accred:unit is the unit name.
        plugin:epfl_accred:unit_id is the unit id.

        Notice: unit_id will be used in the future.
        """
        # configure options
        if unit_name is not None:
            cmd = "option update plugin:epfl_accred:unit {}".format(unit_name.upper())
            self.run_wp_cli(cmd)

        if unit_id is not None:
            # If option not exists
            if not self._option_exists("plugin:epfl_accred:unit_id"):
                cmd = "option add plugin:epfl_accred:unit_id {}".format(unit_id)
                self.run_wp_cli(cmd)
                logging.info("All accred options added")
            else:  # Option exists
                if force:
                    cmd = "option update plugin:epfl_accred:unit_id {}".format(unit_id)
                    self.run_wp_cli(cmd)
                    logging.info("All accred options updated")

        # configure raw plugin
        super(WPAccredConfig, self).configure(force)
