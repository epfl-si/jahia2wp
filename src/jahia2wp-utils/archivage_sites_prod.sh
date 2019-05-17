#!/bin/bash
read -p "Quel est le nom du site? " site 
read -p "Quel est l'url du site? " url 

#Deplacer les fichiers du site
mv /srv/subdomains/${site}.epfl.ch/htdocs /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}

#Editer le fichier .htacess dans subdomains
mkdir /srv/subdomains/${site}.epfl.ch/htdocs
echo "# BEGIN WordPress-Redirects-After-Ventilation" > /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess
echo "RewriteRule ^(.*)$ ${url} [L,QSA,R=301]" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess
echo "# END WordPress-Redirects-After-Ventilation" >> /srv/subdomains/${site}.epfl.ch/htdocs/.htaccess

#Editer le fichier .htacess dans sandbox
sed -i "s|RewriteBase /|RewriteBase /${site}/|g" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/.htaccess
sed -i "s|RewriteRule . /index.php|RewriteRule . /${site}/index.php|g" /srv/sandbox/archive-wp.epfl.ch/htdocs/${site}/.htaccess

#Faire search and replace du site en prod vers archive
wp search-replace ${site}.epfl.ch archive-wp.epfl.ch/${site} --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}

#Activer le coming soon et changer le texte du coming soon
cp activation_coming-soon_archive /tmp/activation_coming-soon_archive_${site}
sed -i "s|sitename|${site}|g" /tmp/activation_coming-soon_archive_${site}
wp option update seed_csp4_settings_content --format=json --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site} < /tmp/activation_coming-soon_archive_${site}
rm /tmp/activation_coming-soon_archive_${site}

#Supprimer le plugin mainwp child
wp plugin deactivate mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}
wp plugin delete mainwp-child --path=/srv/sandbox/archive-wp.epfl.ch/htdocs/${site}
