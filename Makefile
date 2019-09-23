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

/usr/local/bin/composer:
	curl -s https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

vendor: /usr/local/bin/composer
	$(COMPOSER_BIN) install $(COMPOSER_FLAGS)
