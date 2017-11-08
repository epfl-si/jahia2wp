# from wordpress.plugins import WPPluginConfigRestore, WPPluginConfigInfos


def print_plugin_config(plugin_config):
    print("Name      : {}".format(plugin_config.plugin_name))
    print("ZIP path  : {}".format(plugin_config.zip_path))
    print("Is active : {}".format(plugin_config.is_active))
    print("Nb options: {}".format(len(plugin_config.options)))


def print_site_plugins(config_manager, site_name=None):

    for plugin_name, plugin_config in config_manager.plugins(site_name).items():
        print_plugin_config(plugin_config)
        print("")


if __name__ == '__main__':

    # options = yaml.load(open('../data/plugins-config/generic/config-lot1.yml', 'r'))

    # print(options)
    print('hello')
    # y = yaml.load(open('../data/plugins/generic/add-to-any/v1/config-plugin.yml', 'r'))
    # # #
    # print(y)
    #
    # save_file = open('mydic.yml', 'w+')
    # yaml.dump(y, save_file, default_flow_style=False)
    # save_file.close()

    # openshift_env = "lchaboudez"
    # wp_site_url = "http://localhost"
    #
    # # man = WPPluginConfigManager(openshift_env, wp_site_url)
    #
    # """ Backup config test """
    # # ext = WPPluginConfigExtractor(openshift_env, wp_site_url)
    # #
    # # ext.extract_config('outfile.yml')
    #
    # """ Restore test """
    # res = WPPluginConfigRestore(openshift_env, wp_site_url)
    #
    # # plugin_infos = WPPluginConfigInfos('add-to-any',
    # #  yaml.load(open('../data/plugins/generic/add-to-any/v1/config-plugin.yml', 'r')))
    # current_file_path = os.path.dirname(os.path.realpath(__file__))
    # yaml_file = os.path.join(current_file_path, '../data/plugins/generic/add-to-any/v1/config-plugin.yml')
    # plugin_infos = WPPluginConfigInfos('add-to-any', yaml.load(open(yaml_file, 'r')))
    #
    # res.restore_config(plugin_infos)

    # print(site.path)

    # config_manager = WPPluginList('../data/plugins/generic', 'config-lot1.yml', '../data/plugins/specific')
    #
    #
    # print("====== Generic plugins ====== ")
    # print_site_plugins(config_manager)
    #
    # print("====== Plugins for localhost ====== ")
    # print_site_plugins(config_manager, 'localhost')
    # #
    # #
    # # print("")
    # print("====== Plugins for level1 ====== ")
    # print_site_plugins(config_manager, 'level1')
    # #
