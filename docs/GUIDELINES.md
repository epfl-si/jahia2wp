# Guidelines for development

## To use

### String formating
Use `"{} my text".format(value)` everywhere *except* for logging. In this case, use `"%s my text", value`.

Example:

```python
logging.info("WP site %s is nice", wp_site)

print("WP site {} is nice".format(wp_site))
```

### utils.py
We will use 2 differents files for Utils:
- `utils.py` for all functions that can also be reused in other projects.
- `utils-jahia2wp.py`
  - For all functions used only in current project.
  - Only for functions used X times in the project. If only used in one place, code proximity is privileged


### settins.py
Code proximity is privileged. So all settings used only in one place _AND_ not subject to change in a near futur can be out of `settings.py` and located close to code in which it is used.


### Function headers
If a function is not obvious to understand, a small header to explain how it works, the list of parameters and return value needs to be added.


### Multilines string.

Use the following to write multilines strings:

```python
example = Utils.something(
    'Source strings separated by white '
    'space are automatically concatenated by the '
    'interpreter and parenthesis are the natural syntax '
    'for line continuation. Remember to use trailing '
    'spaces.')
```
