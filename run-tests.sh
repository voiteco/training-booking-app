#!/bin/bash
#!/bin/bash
set -e  # Exit immediately if a command exits with a non-zero status
cd /workspace || { echo "Failed to change directory to /workspace"; exit 1; }
php bin/phpunit tests/Controller/Api/BookingControllerTest.php
php bin/phpunit tests/Controller/Api/TrainingControllerTest.php
php bin/phpunit tests/Controller/Api/UserDataControllerTest.php
php bin/phpunit tests/Controller/Api/BookingControllerTest.php
php bin/phpunit tests/Controller/Api/TrainingControllerTest.php
php bin/phpunit tests/Controller/Api/UserDataControllerTest.php
php bin/phpunit tests/Service/DeviceTokenServiceTest.php
php bin/phpunit tests/Service/EmailServiceTest.php
php bin/phpunit tests/Service/GoogleSheetServiceTest.php