.PHONY: test build deploy

# User editable vars
PLUGIN_NAME := php-compatibility-checker

# Shortcuts
DOCKER_RUN := docker run --rm -v `pwd`:/workspace
WP_TEST_IMAGE := worldpeaceio/wordpress-integration:5.3-php7.2
COMPOSER_IMAGE := -v `pwd`:/app -v ~/.composer/cache:/tmp/cache:delegated composer
DEPLOY_IMAGE := wp-deploy
DEPLOY_ENV_VARS = -e SVN_USERNAME -e SVN_PASSWORD -e SLUG="$(PLUGIN_NAME)" -e VERSION="$(shell make get_version)" -e DEPLOY
VENDOR_BIN_DIR := /workspace/vendor/bin
BUILD_DIR := ./build

# Commands
all: composer_install lint test

shell:
	$(DOCKER_RUN) -it --entrypoint "/bin/bash" $(WP_TEST_IMAGE)

composer_install:
	$(DOCKER_RUN) $(COMPOSER_IMAGE) install

lint:
	$(DOCKER_RUN) --entrypoint "$(VENDOR_BIN_DIR)/phpcs" $(WP_TEST_IMAGE) src

phpcbf:
	$(DOCKER_RUN) --entrypoint "$(VENDOR_BIN_DIR)/phpcbf" $(WP_TEST_IMAGE) src

composer_update:
	$(DOCKER_RUN) $(COMPOSER_IMAGE) update

test:
	$(DOCKER_RUN) $(WP_TEST_IMAGE) $(VENDOR_BIN_DIR)/phpunit tests/phpunit

get_version:
	@awk '/Version:/{printf $$NF}' ./src/wpephpcompat.php

build:
	rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)
	rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)-$(shell make get_version).zip
	mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	rsync -r src/ $(BUILD_DIR)/$(PLUGIN_NAME)
	cd $(BUILD_DIR)/ && zip -r $(PLUGIN_NAME)-$(shell make get_version).zip $(PLUGIN_NAME)

deploy:
	docker build -t $(DEPLOY_IMAGE) deploy
	$(DOCKER_RUN) $(DEPLOY_ENV_VARS) $(DEPLOY_IMAGE)
