DOCKER_RUN                             := @docker run --rm
COMPOSER_BASE_CONTAINER                := -v $$(pwd):/app --user $$(id -u):$$(id -g) composer
COMPOSER_WC_SMOOTH_GENERATOR_CONTAINER := -v $$(pwd)/wordpress/wp-content/plugins/wc-smooth-generator:/app --user $$(id -u):$$(id -g) composer:1.10.17
NODE_IMAGE                             := -w /home/node/app -v $$(pwd):/home/node/app --user node phpcompatrevamp
HAS_LANDO                              := $(shell command -v lando 2> /dev/null)
CURRENTUSER                            := $$(id -u)
CURRENTGROUP                           := $$(id -g)
HIGHLIGHT                              :=\033[0;32m
END_HIGHLIGHT                          :=\033[0m # No Color

.PHONY: build
build: build-docker build-assets

.PHONY: build-assets
build-assets: | build-docker-node install-npm
	@echo "Building plugin assets"
	rm -f plugin/languages/*.pot plugin/scripts/*-min.js
	$(DOCKER_RUN) $(NODE_IMAGE) ./node_modules/gulp-cli/bin/gulp.js

.PHONY: build-docker
build-docker: build-docker-node build-docker-php

.PHONY: build-docker-node
build-docker-node:
	if [ ! "$$(docker images | grep phpcompatrevamp)" ]; then \
		echo "Building the Node image"; \
		docker build \
			-f Docker/Dockerfile-node \
			--build-arg UID=$(CURRENTUSER) \
			--build-arg GID=$(CURRENTUSER) \
			-t phpcompatrevamp .; \
	fi

.PHONY: choose-violence
choose-violence:
	npm run wp-env destroy

.PHONY: build-docker-php
build-docker-php:
	if [ ! "$$(docker images | grep woounit)" ]; then \
		echo "Building the PHP image"; \
		docker build -f Docker/Dockerfile-php -t woounit .; \
	fi

.PHONY: clean
clean: clean-assets clean-build

.PHONY: clean-assets
clean-assets:
	@echo "Cleaning up plugin assets"
	rm -rf \
		plugin/languages/*.pot  \
		plugin/scripts/*-min.js

.PHONY: clean-build
clean-build:
	@echo "Cleaning up build-artifacts"
	rm -rf \
		node_modules \
		build \
		vendor \
		clover.xml \
		.phpunit.result.cache


.PHONY: install
install: | clean-assets clean-build
	$(MAKE) install-composer
	$(MAKE) install-npm

.PHONY: install-composer
install-composer:
	$(DOCKER_RUN) $(COMPOSER_BASE_CONTAINER) install

.PHONY: install-npm
install-npm: | build-docker-node
	$(DOCKER_RUN) $(NODE_IMAGE) npm install

.PHONY: release
release: | build-assets wpe-php-compat.zip

.PHONY: reset
reset: stop clean

.PHONY: setup-fakerpress
setup-fakerpress:
	@echo "Setting up fakerpress to generate dummy data"
	npm run wp-env run cli plugin install fakerpress -- --activate

.PHONY: setup-sample-meta
setup-sample-meta:
	@echo "Setting up sample post ids"
	# npm run wp-env run cli post generate -- --count=10 --post_type='post' --post_author='admin' --post_content=`<<< "This is a sample post."`
	for id in `npm run wp-env run cli post "list --post_type=post --field=ID"`; do \
		npm run wp-env run cli post meta update $$id _wpe_my_super_custom_meta "this is the meta for $$id" --format='plaintext'; \
	done

.PHONY: setup-sample-data
echo-sample-data:
	@echo "Setting up sample post data"
	for id in `npm run wp-env run cli post "list --post_type=post --field=ID"`; do echo $$id; done

.PHONY: run-test-e2e
run-test-e2e:
	@echo "Setting up sample post data"
	npm run test:e2e

.PHONY: setup-permalinks
setup-permalinks:
	@echo "Setting up permalinks"
	npm run wp-env run cli option update permalink_structure '/%postname%'

.PHONY: setup
setup:
	@echo "Setting up the project"
	$(MAKE) install
	$(MAKE) build-assets

.PHONY: start
start:
	@echo "Starting WordPress"
	npm run wp-env start
	$(MAKE) setup-permalinks
	$(MAKE) setup-fakerpress

.PHONY: stop
stop:
	@echo "Stopping WordPress"
	npm run wp-env stop

.PHONY: test
test: test-lint test-unit test-e2e

.PHONY: test-e2e
test-e2e: run-test-e2e


.PHONY: test-lint
test-lint: test-lint-php test-lint-javascript

.PHONY: test-lint-javascript
test-lint-javascript: | build-docker-node
	@echo "Running JavaScript linting"
	$(DOCKER_RUN) $(NODE_IMAGE) ./node_modules/jshint/bin/jshint

.PHONY: test-lint-php
test-lint-php:
	@echo "Running PHP linting"
	./vendor/bin/phpcs --standard=./phpcs.xml

.PHONY: php-clean
php-clean:
	@echo "Always cleaning up your messes."
	./vendor/bin/phpcbf --standard=./phpcs.xml

.PHONY: test-unit
test-unit: | build-docker-php
	@echo "Running Unit Tests Without Coverage"
	docker run -v $$(pwd):/app --rm woounit /app/vendor/bin/phpunit

.PHONY: test-unit-coverage
test-unit-coverage: | build-docker-php
	@echo "Running Unit Tests With Coverage"
	docker run -v $$(pwd):/app --rm --user $$(id -u):$$(id -g) woounit /app/vendor/bin/phpunit  --coverage-text --coverage-html build/coverage/

.PHONY: update-composer
update-composer: lando-stop
	$(DOCKER_RUN) $(COMPOSER_BASE_CONTAINER) update
	@echo "Composer updated. If your site had been running please run make start again to access it"

.PHONY: update-npm
update-npm: | build-docker-node
	$(DOCKER_RUN) $(NODE_IMAGE) npm update

wpe-php-compat.zip:
	@echo "Building release file: wpe-php-compat.zip"
	rm -f wpe-php-compat.zip
	cd plugin; zip -r wpe-php-compat.zip *
	mv plugin/wpe-php-compat.zip ./
