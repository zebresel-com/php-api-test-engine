<?php

/**
 * This is a simple API Test-Engine. With simple JSON files you can run tests for all your RESTful API's.
 * 
 * @author Kristof Friess
 * @version 0.1
 * @copyright Copyright (c) since 2014 by Kristof Friess
 */


namespace com\bp;

class APITestEngine
{
    private $count          = 0;        // used to count the tests
    private $countFailes    = 0;        // used to count the failed tests
    private $tmpPath        = null;     // is a writeable tmp directory
    private $mockDir        = null;
    private $url            = 'http://localhost';  // without '/' at the end


    /**
     * Initialize the test engine.
     */
    public function __construct()
    {
        $this->tmpPath = tempnam("/tmp", "COOKIE"); //sys_get_temp_dir();
    }

    /**
     * Set the API URL, this url is use for all tests.
     * @param string $url
     */
    public function setAPIUrl($url)
    {
        $this->url = rtrim($url, '/');
    }

    /**
     * Set the mock dir path. 
     * @param string $path
     */
    public function setMockDir($path)
    {
        if (is_dir($path))
        {
            $this->mockDir = rtrim($path, '/');
        }
        else
        {
            $this->logMsg('ERROR', 'The given mock path is not a directory.');
        }
    }

    /**
     * This method will start the tests readed out of the given directory.
     * @param  string $path Directory Path with tests
     */
    public function run($path)
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if (is_dir($path))
        {
            if ($handle = opendir($path))
            {
                while (false !== ($file = readdir($handle)))
                {
                    if ($file != "." && $file != "..")
                    {
                        $this->testParser($path.DIRECTORY_SEPARATOR.$file);
                    }
                }
                closedir($handle);
            }
        }
        else
        {
            $this->logMsg('ERROR', 'The test path should be a directory with test files.');
        }
    }

    /**
     * This method will log message ot the terminal with color
     * @param  string $t   START, ERROR, SUCCESS or DEBUG
     * @param  string $msg 
     */
    private function logMsg($t, $msg)
    {
        global $colors;
        if($t === 'START')
        {
            // create a log message with white/black colors
            echo "\033[1;38m\033[40m".$msg."\033[0m"."\n";
        }
        else if($t === 'ERROR')
        {
            // create a log message with red/black colors
            echo "\033[0;31m\033[40m".$msg."\033[0m"."\n";
        }
        else if($t === 'SUCCESS')
        {
            // create a log message with green/black colors
            echo "\033[0;32m\033[40m".$msg."\033[0m"."\n";
        }
        else
        {
            // create a log message with light_gray/black colors
            echo "\033[0;37m\033[40m".$msg."\033[0m"."\n";
        }
    }

    /**
     * This method will load a json file located in '<root_dir>/mock'.
     * If the loading will fail. A empty array returns.
     * 
     * @param  string $name  Name of the moc object
     * @return array         Dictionary of the object or an empty array
     */
    private function mockObject($name)
    {
        try
        {
            $objc = file_get_contents($this->mockDir.DIRECTORY_SEPARATOR.$name.'.json');
            return json_decode($objc, true);    
        }
        catch (Exception $e)
        {
            $this->logMsg('ERROR', $e->getMessage());
        }

        return [];
    }

    /**
     * This method will run the test and do the request to the API.
     * @param  string   $name
     * @param  string   $url
     * @param  string   $method GET; POST; PUT; DELETE; ...
     * @param  array    $data
     * @param  function $cb functoin($header, $response) { ... }
     */
    private function test($name, $url, $method, $data, $cb = null)
    {
        if (is_callable($data))
        {
            $cb = $data;
            $data = null;
        }

        $this->logMsg('DEBUG', ' ');
        $this->logMsg('START', "Start Test: {$name}."); ++$this->count;
        $this->logMsg('DEBUG', "\tURL: {$url}");
        $this->logMsg('DEBUG', "\tMETHOD: {$method}");

        try
        {
            // Get cURL resource
            $curl = curl_init();
            $data_string = '';
            // set data
            if ($data !== null && (strtolower($method) == 'post' || strtolower($method) == 'put'))
            {

                // convert data to str
                $data_string = json_encode($data);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
            }
            else if($data !== null && strtolower($method) == 'get')
            {
                $url = $url.'?';
                foreach($data as $key => $value)
                { 
                    $url = $url.$key.'='.$data[$key].'&';
                }

                // remove last AND
                $url = substr($url, -1);
            }

            // Set some options - we are passing in a useragent too here
            curl_setopt_array($curl, array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $url,
                CURLOPT_USERAGENT => 'Zebresel Terminal Tests',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => 1,
                CURLOPT_COOKIESESSION => true,
                CURLOPT_COOKIEFILE => $this->tmpPath,
                CURLOPT_COOKIEJAR => $this->tmpPath,
                CURLOPT_FOLLOWLOCATION => 1,
            ));

            // set the correct method
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));   

            // set header
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(                                                                          
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . mb_strlen($data_string))
            );
            //curl_setopt($curl, CURLOPT_HTTPHEADER,array("Expect:"));

            $before = microtime(true);

            // Send the request & save response to $resp
            $resp = curl_exec($curl);

            // request finished
            $after = microtime(true);

            // Then, after your curl_exec call:
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $headerStr = substr($resp, 0, $headerSize);
            $headers = array();

            foreach (explode("\r\n", $headerStr) as $i => $line)
            {   
                if ($i === 0)
                {
                    $headers['HTTP-Status'] = $line;
                    $tmpLine = explode(' ', $line);
                    $headers['HTTP-Code']   = $tmpLine[1];
                }
                else
                {
                    list ($key, $value) = explode(': ', $line);

                    $headers[$key] = $value;
                }
            }

            $body = substr($resp, $headerSize);
            $json = json_decode($body, true);
            $headers['body'] = $body;

            if (json_last_error() != JSON_ERROR_NONE)
            {
                $json = array('error' => json_last_error_msg());
            }

            $this->logMsg('DEBUG', "\tResponse-Status: {$headers['HTTP-Status']}");
            $this->logMsg('DEBUG', "\tResponse-Time: ". (($after-$before) . " sec\n"));
        
            $result = $cb($headers, $json);

            // Close request to clear up some resources
            curl_close($curl);
            usleep(50);

            if ($result === false)
            {
                ++$this->countFails;
                $body = "\t".str_replace("\n", "\n\t", $body)."\n";
                $this->logMsg('DEBUG', $body);
                $this->logMsg('ERROR', "Test: {$name} failed.");
            }
            else
            {
                $this->logMsg('SUCCESS', "Test: {$name} was successful.");
            }
        } 
        catch (Exception $e)
        {
            ++$this->countFails;
            $this->logMsg('ERROR', "Test: {$name} failed.");
        }

        $this->logMsg('DEBUG', ' ');
    }

    /**
     * This mehtod will print the full test result to the terminal.
     */
    public function printResult()
    {
        if ($this->count > 0)
        {
            $this->logMsg('DEBUG', ' ');
            $this->logMsg('DEBUG', '------------------------------------------------------------------------------------------');
            $this->logMsg('DEBUG', ' ');

            $this->logMsg(($this->countFails!=0?'ERROR':'SUCCESS'), "\tTest finished with {$this->countFails} fails of {$this->count} tests.");
            $this->logMsg('DEBUG', ' ');
            $this->logMsg('DEBUG', '------------------------------------------------------------------------------------------');
            $this->logMsg('DEBUG', ' ');
        }
    }

    /**
     * [recrusiveJsonValidation description]
     * @param  [type] $resp      [description]
     * @param  [type] $valid     [description]
     * @param  [type] &$errCount [description]
     * @return [type]            [description]
     */
    private function recrusiveJsonValidation($resp, $valid, &$errCount)
    {
        foreach ($valid as $key => $value)
        {
            if (isset($resp[$key]))
            {
                if( is_array($value) && is_array($resp[$key]) )
                {
                    $this->recrusiveJsonValidation( $resp[$key], $value, $errCount );
                }
                else if ( !is_array($value) && !is_array($resp[$key]))
                {
                    if ($value === '$nn')
                    {
                        if (!isset($resp[$key]))
                        {
                            ++$errCount;
                            $this->logMsg('ERROR', "Key {$key} is null.");
                        }
                    }
                    else if($value != $resp[$key])
                    {
                        ++$errCount;
                        $this->logMsg('ERROR', "Key {$key} is not equal {$value} != {$resp[$key]}.");
                    }
                }
                else
                {
                    ++$errCount;
                    $this->logMsg('ERROR', "Key {$key} is not equal {$value} != {$resp[$key]}.");
                }
            }
            else
            {
                ++$errCount;
                $this->logMsg('ERROR', "Key {$key} not found.");
            }
        }
    }

    /**
     * This method will search the value inside the given dict using a keypath
     * @param  array    &$dict   
     * @param  string   $keypath e.g. 'account.id'
     * @return mix
     */
    private function valueForKeyPath(&$dict, $keypath)
    {
        $path = explode('.', $keypath);

        $result = &$dict;

        foreach($path as $key)
        {
            $result = &$result[$key];
        }
        
        return $result;
    }

    /**
     * This method will parse a given test (format json) and run them.
     * @param  string $path File path with the test json.
     */
    private function testParser($path)
    {
        // check gloab params already initialized
        if (!is_array($GLOBALS['params']))
        {
            $GLOBALS['params'] = [];
        }

        try
        {
            $filecontent = file_get_contents($path);
            $tests = json_decode($filecontent, true);

            // no json error start tests
            if (json_last_error() === JSON_ERROR_NONE)
            {
                foreach ($tests['tests'] as $test)
                {
                    $requestParams = null;
                    if (isset($test['request_params']))
                    {
                        $requestParams = $test['request_params'];

                        // is string? then a mock is required
                        if (is_string($requestParams))
                        {
                            $requestParams = $this->mockObject($requestParams);
                        }
                    }

                    $path = strtr($test['path'], $GLOBALS['params']);
                    $name = strtr($test['name'], $GLOBALS['params']);

                    $this->test($name, $this->url.$path, $test['method'], $requestParams, function($header, $resp) use ($test) {

                        $validation = $test['validation'];

                        // first check http code
                        if ($header['HTTP-Code'] != $validation['http_code'])
                        {
                            return false;
                        }

                        // check response values
                        if (isset($validation['response_params']))
                        {
                            $errCount = 0;
                            $this->recrusiveJsonValidation($resp, $validation['response_params'], $errCount);
                        
                            if ($errCount > 0)
                            {
                                return false;
                            }
                        }

                        // check response values using mock
                        if (isset($validation['mock']))
                        {
                            $params = $this->mockObject($validation['mock']);
                            $errCount = 0;
                            $this->recrusiveJsonValidation($resp, $params, $errCount);
                        
                            if ($errCount > 0)
                            {
                                return false;
                            }
                        }
                        
                        // if all fine retrieve globales and save them
                        if (isset($test['save_global']))
                        {
                            foreach ($test['save_global'] as $value)
                            {
                                $GLOBALS['params']['{$'.$value['key'].'}'] = $this->valueForKeyPath($resp, $value['keypath']);
                            }   
                        }

                        return true;

                    });
                }
            }
            else
            {
                $this->logMsg('DEBUG', ' ');
                $this->logMsg('ERROR', 'Can not read the json inside: '.$path);
                $this->logMsg('DEBUG', json_last_error_msg());
                $this->logMsg('DEBUG', ' ');
            }
        }
        catch (Exception $e)
        {
            $this->logMsg('ERROR', $e->getMessage());
        }
    }

};