# Guidelines for development

## To use

### String formating
Use `"{} my text".format(value)` everywhere *except* for logging. In this case, use `"%s my text", value`.



## To discuss
- Define criterias to decide if a function is in `utils.py` (reusable only in this/all project? number of use?)
- Define criterias to decide which var declaration are in `settings.py` (if used x times? everything is here?)
