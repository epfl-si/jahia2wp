1. When you run :

    python jahia2wp.py generate-one gcharmier https://localhost/folder 

(venv) www-data@94711cfb4922:/srv/gcharmier/jahia2wp/src$ python jahia2wp.py generate-one gcharmier https://localhost/folder 

    ...
    ERROR 1045 (28000): Access denied for user '6a28f31f5e6f8aa9'@'172.18.0.3' (using password: YES)
    ERROR:root:WP@gcharmier/localhost/folder - WP export - wp_cli failed : Command 'wp --quiet config create --dbname='f25fde48dd99fe3a7a6ca783b2ddf854' --dbuser='6a28f31f5e6f8aa9' --dbpass='jGLdtC$0YJhK1kodqV^Y' --dbhost=db --path='/srv/gcharmier/localhost/htdocs/folder'' returned non-zero exit status 1
    ...

    python jahia2wp.py clean-one gcharmier https://localhost/folder

    Successfully cleaned WordPress site http://localhost/folder


    python jahia2wp.py generate-one gcharmier https://localhost/

    ERROR:root:generator for WP@gcharmier/localhost/ - WP export - wordpress files already found


2. FIX ME : You need to copy/paste the .env file to the root directory
