<?php

if (count($argv) == 1)
{
	echo "\nrun.php -u <api url> -t <test dir> -m <mock dir>\n\n";
	exit(1);
}

// check user request help
if (isset($argv[1]) && $argv[1] == '-h')
{
	echo "\n-------------------------------------------------\n\n";
	echo "Welcome to our php api test engine!\n\n";
	echo "Easy run a script with:\n\n";
	echo "\trun.php -u <api url> -t <test dir> -m <mock dir>\n\n";

	echo "Add some additinal information for benchmarking and multi requests:\n\n";
	echo "\t-u <string>\t URL string of the API\n";
	echo "\t-t <string>\t Directory of the tests JSON files\n";
	echo "\t-m <number>\t Directory of the mock JSON files\n";
	echo "\t-n <number>\t number of test rounds\n";
	echo "\t-c <number>\t number of concurrency requests running at the same time\n";
	echo "\n\n";

	exit(0);
}

// load the class
require_once __DIR__.DIRECTORY_SEPARATOR.'test.class.php';

// setup a instance
$engine = new com\bp\APITestEngine();

$options = getopt("u:t:m:n:c:");

// set default API URL
if (isset($options['u']))
{
	$engine->setAPIUrl($options['u']);
}
else
{
	echo " - API URL missing \n";
}

// set mock direcotry
if (isset($options['m']))
{
	$engine->setMockDir($options['m']);
}


if (isset($options['t']))
{	
	$n = isset($options['n']) ? (int)$options['n'] : 1;
	$c = isset($options['c']) ? (int)$options['c'] : 1;

	// read all tests inside the folder and do
	$engine->run($options['t'], $n, $c);

	// display the result
	$engine->printResult();	

	if ($engine->fails() === 0)
	{
		exit(0);
	}
	else
	{
		exit(1);
	}
}
else
{
	echo " - Tests-Path missing \n";
	exit(1);
}