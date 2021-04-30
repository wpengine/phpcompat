<?php
use Brain\Monkey;

require_once dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';

Monkey\setUp();

/**
 * Now we include any plugin files that we need to be able to run the tests. This
 * should be files that define the functions and classes you're going to test.
 */
require_once dirname( dirname( __FILE__ ) )  . '/plugin/wpe-php-compat.php';
