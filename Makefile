# Define static variables.
cwd         = ${shell pwd}
server-name = couchbase-server

# Include configuration file.
-include tmp/makeconf

# Define configuration variables.
define makeconf
php-version    = $(PHP_VERSION)
sdk-version    = $(SDK_VERSION)
client-image   = couchbase-client:php$(PHP_VERSION)-sdk$(SDK_VERSION)
server-version = $(SERVER_VERSION)
server-image   = couchbase:$(SERVER_VERSION)
code-coverage  = $(CODE_COVERAGE)
client-entrypt = docker run --rm -t -v $$(cwd):/app --link $$(server-name) --env-file tmp/clientenv $$(client-image)
endef
export makeconf

# Define environment variables for client.
define clientenv
COUCHBASE_SERVER_HOST=$(server-name)
COUCHBASE_SERVER_VERSION=$(SERVER_VERSION)
endef
export clientenv

# Configure the test environment.
configure: require-PHP_VERSION require-SDK_VERSION require-SERVER_VERSION
	@ if [ ! -d tmp ]; then mkdir tmp; fi
	@ echo "$$makeconf" > tmp/makeconf
	@ echo "$$clientenv" > tmp/clientenv

# Check configuration.
check-config:
	${if ${wildcard tmp/makeconf}, , ${error Not configured. Run "make configure"}}

# Build the test client image.
build-client: check-config
	sh .ci/client/create-dockerfile.sh --php-version=$(php-version) --sdk-version=$(sdk-version) > tmp/client-dockerfile
	docker build --rm -t $(client-image) -f tmp/client-dockerfile .

# Install the application.
install: check-config
	@ if [ -f composer.lock ]; then rm -f composer.lock; fi
	docker run --rm -t -v $(cwd):/app $(client-image) composer install --no-interaction --prefer-source

# Start the test server.
start-server: check-config
	docker run -d --name $(server-name) $(server-image)
	$(client-entrypt) php -f .ci/server/configure-$(server-version).php

# Run unit tests.
run-test: check-config
ifdef code-coverage
	${eval test-args = --coverage-clover tmp/coverage/clover.xml}
endif
	$(client-entrypt) /app/vendor/bin/phpunit $(test-args)

# Stop the test server.
stop-server:
	-docker stop $(server-name)
	-docker rm -f $(server-name)

# Require a parameter to be defined.
require-%:
	${if $($(*)), , ${error $(*) is required}}
