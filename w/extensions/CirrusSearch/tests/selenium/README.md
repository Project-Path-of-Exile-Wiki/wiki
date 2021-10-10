# Selenium tests

For more information see https://www.mediawiki.org/wiki/Selenium

Tests here are running daily in selenium-CirrusSearch-jessie Jenkins job. For documentation see https://www.mediawiki.org/wiki/Selenium/How-to/Run_tests_using_selenium-daily-SITE-EXTENSION_Jenkins_job

## Usage

In one terminal window or tab start Chromedriver:

    chromedriver --url-base=wd/hub --port=4444

In another terminal tab or window:

    npm ci
    MW_SERVER=https://en.wikipedia.beta.wmflabs.org npm run @selenium-test
