<?php

if (count($argv) == 1)
{
	echo "\nrun.php <api url> <test dir> <mock dir>\n\n";
	exit(1);
}

// load the class
require_once __DIR__.DIRECTORY_SEPARATOR.'test.class.php';

// setup a instance
$engine = new com\bp\APITestEngine();

// set default API URL
if (count($argv) >= 2)
{
	$engine->setAPIUrl($argv[1]);
}
else
{
	echo " - API URL missing \n";
}

// set mock direcotry
if (count($argv) >= 4)
{
	$engine->setMockDir($argv[3]);
}


if (count($argv) >= 3)
{
	// read all tests inside the folder and do
	$engine->run($argv[2]);

	// display the result
	$engine->printResult();	
}
else
{
	echo " - Tests-Path missing \n";
}