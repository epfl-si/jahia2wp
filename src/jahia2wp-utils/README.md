# Utils

This set of Bash files are used to give "shortcuts" to use `jahia2wp.py` commands. Below, a description for each script.

## Scripts configuration
Copy file `config.sample.sh` to `config.sh` and edit it to set correct values for your environment.

## exec-wpcli-on-all-sites.sh
Takes a root path where to find existing WordPress website and then execute given WPCLI command by automatically adding `--path=` parameter.
> ./exec-wpcli-on-all-sites.sh <sitesRootPath> <wpCliToExec>

## clean.sh
This script is used to clean a site by its name. 
> ./clean.sh \<siteName>

## down.sh
Used to download a ZIP file from Jahia. A prompt to enter Jahia admin password will appears script is executed.
> ./down.sh \<siteName>

## export.sh
To export only one site using `jahia2wp.py export` command.
This will be done by default for "idevelop" unit and with `--installs-locked=yes`. 
There's a possibility to give an extra argument (ex: `--debug`) and it will be added to `jahia2wp.py export` command.
> ./export.sh \<siteName> [\<extraArg>]

## export-csv.sh
To export only one site using a CSV file as input. The script takes site name as argument and will then look into migration CSV file to recover the line containing all information. 
A temporary CSV will be generated for website and will then be used by the script to call `jahia2wp.py export-many` command.
Once export is over, temporary file is deleted.
There's a possibility to give an extra argument (ex: `--debug`) and it will be added to `jahia2wp.py export-many` command.
> ./export-many.sh \<siteName> [\<extraArg>]

## parse.sh
To parse a site.
> ./parse.sh \<siteName>
