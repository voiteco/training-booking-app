#!/bin/bash
export XDEBUG_MODE=debug
export XDEBUG_SESSION=PHPSTORM
php -dxdebug.start_with_request=yes bin/console "$@"