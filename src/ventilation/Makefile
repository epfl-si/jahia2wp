# Makefile for the "ventilation" operations

VENTILATION_DIR := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
JAHIA2WP_TOPDIR := $(shell dirname $(shell dirname $(VENTILATION_DIR)))

# The ventilation.csv file is a set of "marching orders" to be
# provided by internal product management teams (not committed to
# version control). Format is source,destination_site,relative_uri

VENTILATION_CSV   = ventilation.csv
SOURCES_INVENTORY = $(VENTILATION_DIR)/hosts/ventilation-source-wordpresses

.PHONY: all
all: wxr-src

$(SOURCES_INVENTORY) hosts/group_vars/ventilation-source-wordpresses: $(VENTILATION_CSV)
	@mkdir hosts 2>/dev/null || true
	python3 ./discover-wordpresses.py $< $(SOURCES_INVENTORY)

wxr-src: $(SOURCES_INVENTORY)
	@mkdir "$@" 2>/dev/null || true
	ansible-playbook $(JAHIA2WP_TOPDIR)/ansible/dump -i $(dir $(SOURCES_INVENTORY)) -l ventilation-source-wordpresses -e "to=$(VENTILATION_DIR)/wxr-src"

clean:
	rm -rf hosts/ wxr-src/

.PHONY: _debug
_debug:
	@echo WXR_TOOLS_DIR=$(WXR_TOOLS_DIR)
	@echo JAHIA2WP_TOPDIR=$(JAHIA2WP_TOPDIR)
