import logging

from .config import WPPluginConfig


class WPAccredConfig(WPPluginConfig):

    def _option_exists(self, option_name):
        cmd = "option get {}".format(option_name)
        return not type(self.run_wp_cli(cmd)) == str

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
