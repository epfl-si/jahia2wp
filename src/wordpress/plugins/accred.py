import logging

from .config import WPPluginConfig


class WPAccredConfig(WPPluginConfig):

    def configure(self, unit_name=None, unit_id=None, **kwargs):
        """
        plugin:epfl_accred:unit is the unit name.
        plugin:epfl_accred:unit_id is the unit id.
        
        Notice: unit_id will be used in the future.
        """
        # configure options
        cmd = "option update plugin:epfl_accred:unit {}".format(unit_name.upper())
        self.run_wp_cli(cmd)

        cmd = "option add plugin:epfl_accred:unit_id {}".format(unit_id)
        self.run_wp_cli(cmd)
        logging.info("All accred options added")

        # configure raw plugin
        super(WPAccredConfig, self).configure()
