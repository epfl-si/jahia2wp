jahia2wp
==


Commandes make 
-

On se connecte à la machine :

<pre><code>ssh www-data@exopgesrv55.epfl.ch -p 32222</code></pre>

A l'heure de l'écriture de ces quelques lignes, je me connecte ainsi :

<pre><code>ssh www-data@kissrv94.epfl.ch -p 32222</code></pre>

#### Rappel des informations pour les différents environnements de DEV

https://env-ej-os-exopge.epfl.ch -> /srv/ejaep

https://env-lv-os-exopge.epfl.ch  -> /srv/lvenries

https://env-lc-os-exopge.epfl.ch  -> /srv/lchaboudez

https://env-lb-os-exopge.epfl.ch  -> /srv/lboatto

https://env-gc-os-exopge.epfl.ch  -> /srv/gcharmier

https://env-eb-os-exopge.epfl.ch  -> /srv/ebreton

<blockquote style="background-color: red; color: white"; font-weight: bold;>
<p>Attention ! Vous devez adapter les commandes qui suivent avec les informations ci-dessus.</p>
</blockquote>

### Configuration des variables d'environnements #

Dans le répertoire <code>/srv/jahia2wp/etc/</code>, il existe 2 fichiers <code>.env</code>

* db.env
* wp.env

On commence par copier ce répertoire dans notre espace personnel. 

<pre><code>cp -r /srv/jahia2wp/etc/ /srv/gcharmier/</code></pre>

#### Modification des variables d'environnements

On adapte les variables d'environnement pour notre espace de développement

Pour le fichier <code>/srv/gcharmier/etc/wp.env</code>

<pre><code>WP_PATH=/srv/gcharmier/env-gc-os-exopge.epfl.ch</code></pre>

<pre><code>WP_DB_NAME=gcwp1</code></pre>

<pre><code>WP_URL=https://env-gc-os-exopge.epfl.ch</code></pre>

<pre><code>WP_TITLE=GCWP1</code></pre>
  
Pour le fichier <code>/srv/gcharmier/etc/db.env</code>

<pre><code>MYSQL_WP_USER=ugcwp1</code></pre>

#### Activation des variables d'environnements

On active les modifications des variables d'environnements :

<pre><code>source /srv/gcharmier/etc/wp.env</code></pre>

<pre><code>source /srv/gcharmier/etc/db.env</code></pre>

### Lancer le make install #

Se positionner dans le root du projet contenant le Makefile :
<pre><code>cd /srv/jahia2wp/</code></pre>

On lance l'installation du site WordPress

<pre><code>make install</code></pre>

On peut vérifier que le site répond :

<pre><code>https://env-gc-os-exopge.epfl.ch/</code></pre>

### Lancer le make clean

Se positionner dans le root du projet contenant le Makefile :
<pre><code>cd /srv/jahia2wp/</code></pre>

On peut supprimer les actions de la commande make install via :
<pre><code>make clean</code></pre>



