COMPOSER_BIN ?= composer

.PHONY: install check test fire-test http-test

install:
	$(COMPOSER_BIN) install --no-plugins --no-scripts --no-interaction --prefer-dist

check:
	$(COMPOSER_BIN) --no-plugins check

test:
	$(COMPOSER_BIN) --no-plugins test

fire-test:
	php tests/Fire/run.php

http-test:
	php tests/Http/run.php
