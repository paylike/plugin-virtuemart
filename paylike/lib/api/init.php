<?php
// Errors
$dirname = __DIR__ .'/';
require($dirname .'Exception/ApiException.php');
require($dirname .'Exception/ApiConnection.php');
require($dirname .'Exception/Conflict.php');
require($dirname .'Exception/Forbidden.php');
require($dirname .'Exception/InvalidRequest.php');
require($dirname .'Exception/NotFound.php');
require($dirname .'Exception/Unauthorized.php');

// Endpoint
require( $dirname .'Endpoint/Endpoint.php' );
require( $dirname .'Endpoint/Apps.php' );
require( $dirname .'Endpoint/Merchants.php' );
require( $dirname .'Endpoint/Transactions.php' );
require( $dirname .'Endpoint/Cards.php' );

// Response
require($dirname .'Response/ApiResponse.php');

// Client
require($dirname .'HttpClient/HttpClientInterface.php');
require($dirname .'HttpClient/CurlClient.php');

// Main
require($dirname .'Paylike.php');


