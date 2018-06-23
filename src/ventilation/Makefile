# Makefile for the "ventilation" operations

VENTILATION_DIR := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
JAHIA2WP_TOPDIR := $(shell dirname $(shell dirname $(VENTILATION_DIR)))

# The ventilation.csv file is a set of "marching orders" to be
# provided by internal product management teams (not committed to
# version control). Format is source,destination_site,relative_uri

VENTILATION_CSV   = ventilation.csv
SOURCES_INVENTORY = $(VENTILATION_DIR)/hosts/ventilation-source-wordpresses

$(SOURCES_INVENTORY) hosts/group_vars/ventilation-source-wordpresses: $(VENTILATION_CSV)
	@mkdir hosts 2>/dev/null || true
	python3 ./discover-wordpresses.py $< $(SOURCES_INVENTORY)

clean:
	rm -rf hosts/

.PHONY: _debug
_debug:
	@echo WXR_TOOLS_DIR=$(WXR_TOOLS_DIR)
	@echo JAHIA2WP_TOPDIR=$(JAHIA2WP_TOPDIR)
