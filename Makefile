# Harbourview Bakery site rescue. Everything is scripted, no admin clicking.
#
#   make photos      download the bakery photos (checksum verified)
#   make before      build the BEFORE site on http://localhost:8881
#   make after       build the AFTER site on http://localhost:8882
#   make reset       wipe both sites (containers + volumes) and rebuild them
#   make measure     Lighthouse, 3 runs per page / form factor, both sites
#   make screenshots desktop + mobile screenshots of both sites
#   make down        stop everything and delete the volumes

SHELL := /bin/bash
NODE_PATH_PREFIX := export PATH="$$HOME/.nvm/versions/node/v22.21.0/bin:$$PATH" &&
COMPOSE := docker compose

.PHONY: photos before after reset down stop up measure screenshots verify-form

# Free stock photos listed in seed/photos.json, downloaded at full size into
# build/photos/ (git ignored) and checked against their sha256.
photos:
	$(NODE_PATH_PREFIX) node scripts/fetch-photos.mjs

before: photos
	$(COMPOSE) up -d db-before wp-before
	$(COMPOSE) run --rm -T cli-before sh /work/seed/seed.sh
	@echo "BEFORE ready: http://localhost:8881"

after: photos
	$(COMPOSE) up -d db-after wp-after mailpit
	$(COMPOSE) run --rm -T cli-after sh /work/seed/seed.sh
	$(COMPOSE) run --rm -T cli-after sh /work/after/rescue.sh
	@echo "AFTER ready: http://localhost:8882"

reset: down
	$(MAKE) before after

up:
	$(COMPOSE) up -d db-before wp-before db-after wp-after mailpit

stop:
	$(COMPOSE) stop

down:
	$(COMPOSE) --profile cli down -v --remove-orphans

# Submits the cake order form on the AFTER site and checks Mailpit got it.
verify-form:
	$(NODE_PATH_PREFIX) node scripts/verify-form.mjs

measure:
	$(NODE_PATH_PREFIX) node scripts/measure.mjs

screenshots:
	$(NODE_PATH_PREFIX) node scripts/screenshots.mjs
