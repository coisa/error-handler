COMPOSER_BIN := composer

COMPOSER_FLAGS := --prefer-dist \
				--no-scripts \
				--no-progress \
				--no-suggest \
				--optimize-autoloader \
				--no-ansi \
				--no-interaction \
				--no-plugins

.PHONY: install uninstall reinstall clean vendor

install: vendor
uninstall: clean
reinstall: clean vendor

clean:
	rm -rf vendor

vendor:
	$(COMPOSER_BIN) install $(COMPOSER_FLAGS)
