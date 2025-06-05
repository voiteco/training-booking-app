#!/bin/bash
cd /workspace
php bin/phpunit tests/Controller/Api/BookingControllerTest.php
php bin/phpunit tests/Controller/Api/TrainingControllerTest.php
php bin/phpunit tests/Controller/Api/UserDataControllerTest.php
php bin/phpunit tests/Service/DeviceTokenServiceTest.php
php bin/phpunit tests/Service/EmailServiceTest.php
php bin/phpunit tests/Service/GoogleSheetServiceTest.php