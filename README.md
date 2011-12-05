SalesNet PHP Objects
====================

This repository provides two objects to make it a little easier to work with the [SalesNet CRM](http://www.salesnet.com/) SOAP API from within PHP. The first object works with the SalesNet authentication service to retrieve an authentication token while the second is a simple proxy to trap calls to the various SalesNet endpoints and to return the results. Both objects utilise lazy loading so that no requests are sent to SalesNet until they are needed and include full unit tests. 

The Authentication Object
-------------------------

The SalesNet API requires that before any requests are made to the various service endpoints a user must authenticate against the security endpoint. This service returns a token which is valid for up to 12 hours (depending on the settings in the users SalesNet account) and which must be set as a SOAP header in all subsequent requests. The authentication object accepts a company login, username and password. When the doLogin() method is called it will authenticate against the security endpoint and return the token that is returned in a PHP SoapHeader object. Subsequent calls to doLogin() will return the SoapHeader object with the token that has already been retrieved. The user can set the number of hours that the token should be considered valid for, with the default being 12 hours. This object can be serialized and stored in a session or a cache, allowing the security token to be shared across multiple requests.

The SoapProxy Object
--------------------

The SoapProxy object is much simpler than the Authentication object in many ways. It uses PHP's magic __call() method to trap calls to the API, returning the results for the user to process. The SoapProxy object accepts an instance of WebServices\Soap\SalesNet\Authentication which it uses internally to fetch the authentication SoapHeader to successfully call the API. SOAP options can be provided for the PHP SoapClient used in the object and the SoapClient can also be retrieved if needed.

Example Usage
-------------

The example below illustrates how these classes may be used to call the GetDeals method of the SalesNet deals endpoint. Note that no attempt is made to either guess the arguments needed by the method or to process the return value. For more information on both of these the reader is directed to the SalesNet API documentation.

	<?php
	use WebServices\Soap\SalesNet\Authentication, WebServices\Soap\SalesNet\SoapProxy;
	//Set authentication. This will assume that the token expiry time is 12 hours.
	$auth = new Authentication();
	$auth->setCompanyLogin('YOUR COMPANY LOGIN)
		 ->setUserName('YOUR USERNAME)
		 ->setPassword('YOUR PASSWORD');
	
	$soap = new SoapProxy(SoapProxy::DEALS, $auth);
	$args = array(
		//Setup SOAP method arguments here
	);
	$result = $soap->GetDeals($args);
	?>

For more details please see comments in the code for the classes.