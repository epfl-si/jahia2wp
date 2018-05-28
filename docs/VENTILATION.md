Ventilation Guideline
--------------------

## Quick start

The process is just one subcommand under jahia2wp.py. The usual way to call it is:

```bash
PYTHONIOENCODING="utf-8" python jahia2wp.py migrate-urls $CSV_FILE $WP_ENV --root_wp_dest=$ROOT_WP_DEST --strict
```
The IO Encoding is mandatory since the docker containers use a variant of ascii for io while utf8 for the system.

The CSV file path with the rules. IMPORTANT: It has to be validated (syntax, semantic) before running the URL 
migration. 

The root\_wp\_dest is where the EPFL destination site is located, should be /srv/$WP\_ENV/www.epfl.ch

The *strict* parameter is important, it only lets migrate explicit matching URLs (i.e. if no star *, the subpages are not 
migrated). If not specified *greedy* mode takes place and it assumes there are stars everywhere to migrate most 
of the content. This behavior will change to set strict mode by default in the future. 

## Get started

There are some demo scripts for the 'ventilation' process under vent-demo in the src folder, 
they cover different case scenarios and help to understand how the process works.

