# Guidelines for development

## To use

### String formating
Use `"{} my text".format(value)` everywhere *except* for logging. In this case, use `"%s my text", value`.

Example:

```python
logging.info("WP site %s is nice", wp_site)

print("WP site {} is nice".format(wp_site))
```

## To discuss
- Define criterias to decide if a function is in `utils.py` (reusable only in this/all project? number of use?)
..- Function is used in more than one script file. If only used in one file (even several times), the function is
located in the file to increase code proximity and readability.


- Define criterias to decide which var declaration are in `settings.py` (if used x times? everything is here?)
..- Var is used in more than one script file. If only used in one file (even several times), the var is
located in the file to increase code proximity and readability.


- Always write a function header (comments) to explain function, parameters and possible returns.


- String on multilines:
Example:

```python
example = Utils.something(
    'Source strings separated by white '
    'space are automatically concatenated by the '
    'interpreter and parenthesis are the natural syntax '
    'for line continuation. Remember to use trailing '
    'spaces.')
```
