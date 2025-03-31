#!/bin/bash
php -d memory_limit=512M vendor/bin/phpstan analyse "$@"