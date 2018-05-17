if [ -n "$ROOT_SITE" ]; then
 	ROOT_SITE=www.epfl.ch
fi

 DEMO_SITE=/srv/$WP_ENV/dcsl.epfl.ch

# Switch to the src/ path.
cd /srv/$WP_ENV/jahia2wp/src/;

# ======= DEMO CODE ======
# Check if the destination root tree (arborescence) exists.
demo_site1="$ROOT_WP_DEST/htdocs/research/domains/laboratories/"
demo_site2="$ROOT_WP_DEST/htdocs/research/domains/ic/"
rmdir $demo_site1 $demo_site2;
if [ ! -d $demo_site1 -o ! -d $demo_site2 ]; then
	echo "Destination root tree does not exist: $ROOT_WP_DEST, generating sample sites...";
	mkdir -p $demo_site1 $demo_site2;
	python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/laboratories/dcsl --admin-password=admin --extra-config=vent-demo/data/generate.yml;
	python jahia2wp.py generate $WP_ENV http://$ROOT_SITE/research/domains/ic/dcsl --admin-password=admin --extra-config=vent-demo/data/generate.yml;
	# Move the accred and tequila plugins to let for local connections
	find /srv/$WP_ENV/$ROOT_SITE/ -type d \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;
fi

# Check if the dcsl.epfl.ch folder exists
if [ ! -d $DEMO_SITE ]; then
	echo "Demo site dir does not exsit: $DEMO_SITE, calling exportmany.sh...";
	echo "################################"
	echo "IMPORTANT: If you are running on a local env, add an entry to the /etc/hosts of the mgmt container like:";
	echo "172.19.0.5	dcsl.epfl.ch"
	echo ", otherwise the REST api will fail without access to port 8080"
	echo "If you want to see the intermediate WP site https://dcsl.epfl.ch, also add an entry to your local /etc/hosts :"
	echo "127.0.0.1	dcsl.epfl.ch"
	echo "################################"
	ips=`getent ahostsv4 hosts dcsl.epfl.ch | awk '{ print $1 }'`
	if [[ ! $ips = *"172.19.0."* ]]; then
		echo "Please make sure the /etc/vhosts has an entry for dcsl.epfl.ch as above."
		exit;
	fi

	# Export the site
	PYTHONIOENCODING="utf-8" python jahia2wp.py export-many vent-demo/data/exportmany.csv --admin-password=admin

	# Disable accred and tequila
	echo "Disabling accred and tequila plugins from $DEMO_SITE ...";
	find $DEMO_SITE -type d \( -iname "accred" -o -iname "tequila" \) -exec mv {} {}.bak \;
fi
