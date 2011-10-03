<?php
namespace WebServices\Soap\SalesNet;

use SoapClient;
use SoapFault;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use BadMethodCallException;
use ReflectionClass;
/**
 * Class to proxy calls to the various SalesNet web services.
 * Calls to the various soap methods are caught by __call and passed through to a SoapClient to complete.
 * The results of SOAP calls are returned with no processing to the user to handle.
 * Authentication to SalesNet is handled by passing a SalesNet login object with the appropriate credentials to the class constructor.
 * 
 * For example, assuming a SoapProxy object of $proxy, an array of arguments of $args and an Authentication object of $auth the following would call the GetDeals method of the Deals endpoint and return the results:
 * 
 * $proxy = new SoapProxy(SoapProxy::DEALS, $auth);
 * $results = $proxy->GetDeals($args); 
 * 
 * @author Jeremy Cook
 * @version 1.0
 */
class SoapProxy {
	/**
	 * 
	 * Constants for the various SalesNet SOAP endpoints.
	 */
	const CLIENTS = 'https://wsapi.salesnet.com/version_4/account.asmx?WSDL';
	const DEALS = 'https://wsapi.salesnet.com/version_4/deal.asmx?WSDL';
	const CONTACTS = 'https://wsapi.salesnet.com/version_4/contact.asmx?WSDL';
	const LEADS = 'https://wsapi.salesnet.com/version_4/lead.asmx?WSDL';
	const CAMPAIGNS = 'https://wsapi.salesnet.com/version_4/campaign.asmx?WSDL';
	const ACTIVITY = 'https://wsapi.salesnet.com/version_4/activity.asmx?WSDL';
	const DOCUMENT = 'https://wsapi.salesnet.com/version_4/document.asmx?WSDL';
	const COMPANY = 'https://wsapi.salesnet.com/version_4/company.asmx?WSDL';
	const UTILITY = 'https://wsapi.salesnet.com/version_4/utility.asmx?WSDL';
	const SYSTEM = 'http://wsapi.salesnet.com/version_4/system.asmx?WSDL';
	/**
	 * Url of the SOAP service to use
	 * 
	 * @var string
	 */
	protected $SoapUrl;
	/**
	 * SoapClient object
	 * 
	 * @var \SoapClient
	 */
	private $SoapClient;
	/**
	 * Array of SoapOptions to use, if any
	 * 
	 * @var array
	 */
	protected $SoapOpts = array();
	/**
	 * Login object to use to get an authentication SoapHeader
	 * 
	 * @var Login
	 */
	protected $Auth;
	
	/**
	 * Constructor
	 * 
	 * @param string $SoapServiceUrl The name of the Soap Service to use. Should be one of the class constants if set
	 * @param Authentication $auth Authentication object to get a SoapHeader from.
	 */
	public function __construct($SoapServiceUrl = '', Authentication $auth = NULL) {
		if ($SoapServiceUrl)
			$this->setSoapUrl($SoapServiceUrl);
		if ($auth)
			$this->Auth = $auth;
	}
	
	/**
	 * Method to trap any calls to the SOAP client.
	 * This makes the class act as a proxy for calls to the SalesNet API through the object.
	 * 
	 * @param string $name Name of SOAP method to call
	 * @param array $arguments Optional array of arguments to the method
	 * @return \stdClass
	 * @throws \BadMethodCallException
	 */
	public function __call($name, array $arguments = array()) {
		try {
			switch(true) {
				case (! $this->SoapUrl) :
					throw new BadMethodCallException(sprintf('SoapUrl must be set before calling %s in %s', $name, __METHOD__));
				case (! $this->Auth) :
					throw new BadMethodCallException(sprintf('Authentication object must be set before calling %s in %s', $name, __METHOD__));
				default :
					return $this->getSoapClient()->__soapCall($name, $arguments);
			}
		} catch(SoapFault $e) {
			throw new BadMethodCallException(sprintf('Error calling method %s in %s. See the SoapFault object for more details.', $name, __METHOD__), $e->getCode(), $e);
		}
	}
	/**
	 * Sleep function to control serialization.
	 * 
	 * @return array
	 */
	public function __sleep() {
		return array('SoapUrl', 'SoapOpts', 'Auth');
	}
	/**
	 * Method to set Soap options to use if any.
	 * 
	 * @param array $SoapOpts
	 * @return WebServices\Soap\SalesNet\SoapProxy
	 * @see http://www.php.net/manual/en/soapclient.soapclient.php
	 */
	public function setSoapOpts(array $SoapOpts) {
		$this->SoapOpts = $SoapOpts;
		return $this;
	}
	/**
	 * 
	 * Method to set a Login object to perform authentication against the SalesNet security API.
	 * @param Authentication $auth
	 * @return WebServices\Soap\SalesNet\SoapProxy
	 */
	public function setAuthentication(Authentication $auth) {
		$this->Auth = $auth;
		return $this;
	}
	/**
	 * 
	 * Method to set the SOAP endpoint URL for the object.
	 * The submitted url is validated against the class constants for the various SalesNet endpoints.
	 * @param string $SoapServiceUrl Should be one of the class constants for the SalesNet endpoints
	 * @throws InvalidArgumentException
	 * @return \WebServices\Soap\SalesNet\SoapProxy
	 */
	public function setSoapUrl($SoapServiceUrl) {
		$reflect = new ReflectionClass('\WebServices\Soap\SalesNet\SoapProxy');
		foreach($reflect->getConstants() as $url) {
			if ($SoapServiceUrl === $url) {
				/**
				 * 
				 * Reset the Soap client. 
				 * This will ensure that the next SOAP call will get a new soap client with the new url 
				 */
				$this->SoapClient = NULL;
				$this->SoapUrl = $SoapServiceUrl;
				return $this;
			}
		}
		throw new InvalidArgumentException(sprintf('Invalid url "%s" passed in %s', $SoapServiceUrl, __METHOD__));
	}
	/**
	 * Method to return the SoapClient object, initiating a connection if one doesn't already exist.
	 * 
	 * @return \SoapClient
	 * @throws \SoapFault
	 */
	protected function getSoapClient() {
		switch(true) {
			case (! $this->SoapClient instanceof SoapClient) :
				if ($this->SoapOpts) {
					$this->SoapClient = new SoapClient($this->SoapUrl, $this->SoapOpts);
				} else {
					$this->SoapClient = new SoapClient($this->SoapUrl);
				}
			default :
				$this->SoapClient->__setSoapHeaders($this->Auth->doLogin());
				return $this->SoapClient;
		}
	}
}
?>
