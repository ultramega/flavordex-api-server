<?php

/*
 * The MIT License
 *
 * Copyright 2016 Steve Guidetti.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

use Flavordex\Exception\BadRequestException;
use Flavordex\Exception\HttpException;
use Flavordex\Exception\InternalErrorException;
use Flavordex\Exception\NotFoundException;

spl_autoload_register(function ($className) {
    $filePath = str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    if(file_exists($filePath)) {
        require_once $filePath;
    }
});

set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
    (new InternalErrorException("An unknown error has occurred"))->output();
    return false;
});

set_exception_handler(function (\Exception $exception) {
    if($exception instanceof HttpException) {
        $exception->output();
    }
});

$filterOptions = array(
    'options' => array(
        'regexp' => '/^\w+$/'
    )
);
$endpoint = filter_input(INPUT_GET, 'endpoint', FILTER_VALIDATE_REGEXP, $filterOptions);
if(!$endpoint) {
    throw new BadRequestException('Invalid endpoint name');
}
$method = filter_input(INPUT_GET, 'method', FILTER_VALIDATE_REGEXP, $filterOptions);
if(!$method) {
    throw new BadRequestException('Invalid method name');
}
$filterOptions['options']['regexp'] = '/^[\w\/-]*$/';
$param = filter_input(INPUT_GET, 'params', FILTER_VALIDATE_REGEXP, $filterOptions);
if($param === false) {
    throw new BadRequestException('Invalid parameter string');
}


$className = 'Flavordex\\Endpoint\\' . ucfirst($endpoint) . 'Endpoint';
if(!class_exists($className)) {
    throw new NotFoundException('The "' . $endpoint . '" endpoint does not exist');
}
$endpointObject = new $className();

if(!method_exists($endpointObject, $method)) {
    throw new NotFoundException('The "' . $method . '" method does not exist on the "' . $endpoint . '" endpoint');
}

$params = explode('/', $param);
$numParams = !$param ? 0 : count($params);
$reqParams = (new ReflectionMethod($className, $method))->getNumberOfRequiredParameters();
if($numParams != $reqParams) {
    throw new BadRequestException('The "' . $method . '" method requires ' . $reqParams . ' parameter(s) (' . $numParams . ' given)');
}

header('Content-Type: text/plain; charset=utf-8');
$response = call_user_func_array(array($endpointObject, $method), $params);
if($response !== false && $response !== null) {
    if(is_array($response) || is_object($response)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
    } else {
        echo $response;
    }
}
