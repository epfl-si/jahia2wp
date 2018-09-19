# Makefile for the "ventilation" operations
#
# Example usages:
#
#   make clean all push
#
#   make push PUSH_EXTRA_VARS='{"wp_destructive":{"vpsi":"wipe"},"wp_unit_id":"VPSI"}'
#
# CAVEAT EMPTOR: the latter command *WIPES OUT* the "vpsi" remote site!

VENTILATION_DIR := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
JAHIA2WP_TOPDIR := $(shell dirname $(shell dirname $(VENTILATION_DIR)))

# The ventilation.csv file is a set of "marching orders" to be
# provided by internal product management teams (not committed to
# version control). Format is source,destination_site,relative_uri

VENTILATION_CSV   = ventilation.csv
SOURCES_INVENTORY = $(VENTILATION_DIR)/hosts/ventilation-source-wordpresses
TARGETS_INVENTORY = $(VENTILATION_DIR)/hosts/ventilation-target-wordpresses

.PHONY: all
all: wxr-ventilated

$(SOURCES_INVENTORY) hosts/group_vars/ventilation-source-wordpresses \
$(TARGETS_INVENTORY) hosts/group_vars/ventilation-target-wordpresses: \
  $(VENTILATION_CSV)
	@mkdir hosts 2>/dev/null || true
	python3 ./wordpress-inventories.py --sources=$(SOURCES_INVENTORY) --targets=$(TARGETS_INVENTORY) $<

wxr-src: $(SOURCES_INVENTORY)
	@mkdir "$@" 2>/dev/null || true
	$(JAHIA2WP_TOPDIR)/ansible/dump -i $(dir $(SOURCES_INVENTORY)) -l ventilation-source-wordpresses -e "to=$(VENTILATION_DIR)/wxr-src"

wxr-ventilated: wxr-src $(VENTILATION_CSV)
	python3 ./ventilate.py $(VENTILATION_CSV) wxr-src $@

.PHONY: push
push: wxr-ventilated
	ansible-playbook $(JAHIA2WP_TOPDIR)/ansible/playbooks/push-ventilated.yml  -i $(dir $(TARGETS_INVENTORY)) -l ventilation-target-wordpresses -e "from=$(VENTILATION_DIR)/wxr-ventilated" -e '$(PUSH_EXTRA_VARS)'

clean:
	rm -rf hosts/ wxr-src/ wxr-ventilated/

.PHONY: _debug
_debug:
	@echo WXR_TOOLS_DIR=$(WXR_TOOLS_DIR)
	@echo JAHIA2WP_TOPDIR=$(JAHIA2WP_TOPDIR)
