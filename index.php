<?php
require "SimpleHttpServer.php";
$server = new SimpleHttpServer();
$server->listen(function($request) {
    echo "$request[method] $request[path]\n";
    if ($request['path'] === '/hello') {
        
        return "Hello World!";
    }
    
    return [
        'status' => 404,
        'body' => 'Not Found'
    ];
});