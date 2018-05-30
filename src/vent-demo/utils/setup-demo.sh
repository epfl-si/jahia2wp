DEMO_SITE=/srv/$WP_ENV/dcsl.epfl.ch/htdocs

# Switch to the src/ path.
cd /srv/$WP_ENV/jahia2wp/src/;

# ======= DEMO CODE ======
# Check if the destination root tree (arborescence) exists.
demo_site1="$ROOT_WP_DEST/htdocs/research/domains/laboratories/dcsl"
demo_site2="$ROOT_WP_DEST/htdocs/research/domains/ic/dcsl"
# If dir empty, just delete it.
if [ -d "$demo_site1" -a ! "$(ls -A $demo_site1)" ]; then rmdir $demo_site1; fi
if [ -d "$demo_site2" -a ! "$(ls -A $demo_site2)" ]; then rmdir $demo_site2; fi
if [ ! -d $demo_site1 -o ! -d $demo_site2 ]; then
	echo "Destination root tree does not exist, generating sample sites...";
	mkdir -p $demo_site1 $demo_site2;
	python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/laboratories/dcsl --admin-password=admin --extra-config=vent-demo/data/generate.yml;
	python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/ic/dcsl --admin-password=admin --extra-config=vent-demo/data/generate.yml;
	# Move the accred and tequila plugins to let for local connections
	find /srv/$WP_ENV/$ROOT_SITE/ -type d \( -iname "accred" -o -iname "tequila" \) -print0 | xargs -0 -I {} mv {} {}.bak;
fi

# If dir empty, just delete it.
if [ -d "$DEMO_SITE" -a ! "$(ls -A $DEMO_SITE)" ]; then rmdir $DEMO_SITE; fi
# Check if the dcsl.epfl.ch folder exists
if [ ! -d $DEMO_SITE ]; then
	echo
	echo "Demo site dir does not exist: $DEMO_SITE, calling jahia2wp export...";
	echo "################################"
	echo "IMPORTANT: If you are running on a local env, add an entry to the /etc/hosts of the mgmt container like:";
	echo "172.19.0.5	dcsl.epfl.ch"
	echo ", otherwise the REST api will fail without access to port 8080"
	echo "If you want to see the exported WP site https://dcsl.epfl.ch, also add an entry to your local /etc/hosts :"
	echo "127.0.0.1		dcsl.epfl.ch"
	echo "################################"
	echo
	ips=`getent ahostsv4 hosts dcsl.epfl.ch | awk '{ print $1 }'`
	if [[ ! $ips = *"172.19.0."* ]]; then
		echo "Please make sure the /etc/vhosts has an entry for dcsl.epfl.ch as above."
		echo
		exit;
	fi

	# Export the site
	demo_site_export='/tmp/j2wp_demosite.csv'
	header='wp_site_url,wp_tagline,wp_site_title,site_type,openshift_env,category,theme,theme_faculty,status,installs_locked,updates_automatic,langs,unit_name,Jahia_zip,comment'
	site_demo='https://dcsl.epfl.ch,#parser,#parser,wordpress,gcharmier,GeneralPublic,epfl-master,#parser,yes,no,yes,#parser,DCSL,dcsl,'
	echo $header > $demo_site_export;
	echo $site_demo >> $demo_site_export;
	echo "**** Make sure the wp_exporter has port 8080 to enable the API Rest during export. By default only for jahia2wp-httpd"
	PYTHONIOENCODING="utf-8" python jahia2wp.py export-many $demo_site_export --admin-password=admin;

	# Disable accred and tequila
	echo "Disabling accred and tequila plugins from $DEMO_SITE ...";
	find $DEMO_SITE -type d \( -iname "accred" -o -iname "tequila" \) -print0 | xargs -0 -I {} mv {} {}.bak;
fi

exit 0