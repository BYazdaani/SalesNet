<?php
namespace WebServices\Soap\SalesNet;

use SoapClient;
use SoapHeader;
use InvalidArgumentException;
use BadMethodCallException;
use DateTime;
use DateTimeZone;
use ReflectionClass;
/**
 * Class to retrieve a security token from the SalesNet V4 Authentication API.
 * SalesNet requires that all users use it's authentication API to retrieve a security token. This token must then be set as a SOAP header with all subsequent requests.
 * Security tokens are valid from 1-12 hours, depending on account settings.
 * This class allows parameters to be set and a doLogin method to be called to return an instance of SoapHeader with the token set.
 * It uses lazy loading so that a SoapClient connection will only be established once the doLogin method is called.
 * It also tracks the expiry time of the SoapHeader and will automatically fetch a new token when the current one has expired.
 * This object can be serialized and stored (eg. in a session, APC, memcache or a database) so that the security token can be shared between different requests.
 *
 * This class is designed to be used with the SoapProxy class to provide authentication for it but it can be used indepently.
 *
 * @author Jeremy Cook
 * @version 1.0
 */
class Authentication {
	/**
	 * The Company login name
	 *
	 * @var string
	 */
	protected $CompanyLogin;
	/**
	 * Username
	 *
	 * @var string
	 */
	protected $UserName;
	/**
	 * Password
	 *
	 * @var string
	 */
	protected $Password;
	/**
	 * Instance of SoapClient to use
	 *
	 * @var \SoapClient
	 */
	protected $SoapClient;
	/**
	 * Options to pass to the client if any are set
	 *
	 * @var array
	 */
	protected $SoapOpts;
	/**
	 * Url of the soap endpoint
	 *
	 * @var string
	 */
	CONST SOAP_URL = 'https://security.salesnet.com/version_4/security.asmx?WSDL';
	/**
	 * Soap header produced from the token returned from SalesNet
	 *
	 * @var \SoapHeader
	 */
	protected $SoapHeader;
	/**
	 * Time that the authentication token from SalesNet will expire.
	 *
	 * @var \DateTime
	 */
	protected $ExpiryTime;
	/**
	 *
	 * String to be used to set the expriy time when the DateTime ExpiryTime property is set
	 * @var string
	 */
	protected $TokenExpiryString;
	/**
	 *
	 * Constructor. Sets the expiry time for the security token, which must be between 1 and 12 hours
	 * @param int $tokenValidHours Defaults to 12 hours
	 * @throws InvalidArgumentException
	 */
	public function __construct($tokenValidHours = 12) {
		$options = array(
			'options' => array(
				'min_range' => 1,
				'max_range' => 12
			)
		);
		switch(TRUE) {
			case filter_var($tokenValidHours, FILTER_VALIDATE_INT, $options) === FALSE :
				throw new InvalidArgumentException(sprintf('The number of hours for the token to be valid must be between 1 and 12, "%s" passed in %s', $tokenValidHours, __METHOD__));
			default :
				$this->TokenExpiryString = sprintf('+%s hours', $tokenValidHours);
		}
	}
	/**
	 * Method to control serialization
	 *
	 * @return array
	 */
	public function __sleep() {
		return array(
			'CompanyLogin',
			'Password',
			'UserName',
			'ExpiryTime',
			'SoapHeader',
			'SoapOpts',
			'TokenExpiryString'
		);
	}
	/**
	 * Method to set the company login name
	 *
	 * @param string $CompanyLogin
	 * @return WebServices\Soap\SalesNet\Authentication
	 * @throws \InvalidArgumentException
	 */
	public function setCompanyLogin($CompanyLogin) {
		if (! is_string($CompanyLogin)) {
			throw new InvalidArgumentException(sprintf('Argument "%s" passed to %s must be a string', $CompanyLogin, __METHOD__));
		}
		$this->CompanyLogin = $CompanyLogin;
		return $this;
	}
	
	/**
	 * Getter for the company login
	 * @return string
	 */
	public function getCompanyLogin() {
		return $this->CompanyLogin;
	}
	
	/**
	 * Method to set the Username
	 *
	 * @param string $UserName
	 * @return WebServices\Soap\SalesNet\Authentication
	 * @throws \InvalidArgumentException
	 */
	public function setUserName($UserName) {
		if (! is_string($UserName)) {
			throw new InvalidArgumentException(sprintf('Argument "%s" passed to %s must be a string', $UserName, __METHOD__));
		}
		$this->UserName = $UserName;
		return $this;
	}
	
	/**
	 * Getter for the username
	 * @return string
	 */
	public function getUserName() {
		return $this->UserName;
	}
	
	/**
	 * Method to set the password
	 *
	 * @param string $Password
	 * @return WebServices\Soap\SalesNet\Authentication
	 * @throws \InvalidArgumentException
	 */
	public function setPassword($Password) {
		if (! is_string($Password)) {
			throw new InvalidArgumentException(sprintf('Argument "%s" passed to %s must be a string', $Password, __METHOD__));
		}
		$this->Password = $Password;
		return $this;
	}
	
	/**
	 * Getter for the password
	 * @return string
	 */
	public function getPassword() {
		return $this->Password;
	}
	
	/**
	 * Method to set Soap options to use if any
	 *
	 * @param array $SoapOpts Array of soap options that can be set in a PHP SoapClient constructor
	 * @return \WebServices\Soap\SalesNet\Authentication
	 * @see http://www.php.net/manual/en/soapclient.soapclient.php
	 */
	public function setSoapOpts(array $SoapOpts) {
		$this->SoapOpts = $SoapOpts;
		return $this;
	}
	
	/**
	 * Getter for the soapopts
	 * @return array
	 */
	public function getSoapOpts() {
		return $this->SoapOpts;
	}
	
	/**
	 * Setter for the expiry time to allow premature expiry of the object
	 * @param \DateTime $ExpiryTime
	 * @return \WebServices\Soap\SalesNet\Authentication
	 */
	public function setExpiryTime(DateTime $ExpiryTime) {
		$this->ExpiryTime = $ExpiryTime;
		return $this;
	}
	
	/**
	 * Method to return the expiry time of the authentication token
	 *
	 * @return \DateTime
	 */
	public function getExpiryTime() {
		return $this->ExpiryTime;
	}
	
	/**
	 * Method to perform the login
	 *
	 * @return \SoapHeader
	 * @throws \BadMethodCallException
	 */
	public function doLogin() {
		switch(true) {
			case ! $this->CompanyLogin :
			case ! $this->UserName :
			case ! $this->Password :
				throw new BadMethodCallException(sprintf('Please ensure that company name, username and password are set before calling %s', __METHOD__));
			case (! $this->SoapHeader instanceof SoapHeader) :
			case (! $this->ExpiryTime instanceof DateTime) :
			case (time() >= $this->ExpiryTime->getTimestamp()) :
				//Perfom a new login and set the Soap Header info
				$args = array(
					'cmpLogin' => $this->CompanyLogin,
					'usrLogin' => $this->UserName,
					'usrPassword' => $this->Password
				);
				$token = $this->getSoapClient()->login($args)->token;
				$this->SoapHeader = new SoapHeader('http://www.salesnet.com/wsapi/', 'Authentication', array('token' => $token));
				//Set the Expiry time for the token. Use the UTC timezone as this is what is used by SalesNet.
				$this->ExpiryTime = new DateTime($this->TokenExpiryString, new DateTimeZone('UTC'));
			default :
				//Return the Soap Header
				return $this->SoapHeader;
		}
	}
	
	/**
	 *
	 * Method to reset all of the properties of this object.
	 * @return WebServices\Soap\SalesNet\Authentication
	 */
	public function reset() {
		$reflect = new ReflectionClass($this);
		foreach($reflect->getProperties() as $prop) {
			/**
			 * Reset all properties except for the token expiry string.
			 * The expiry time can be reset with the setExpiryTime method
			 */
			if ($prop !== 'TokenExpiryString')
				$this->$prop = NULL;
		}
		return $this;
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
				if (is_array($this->SoapOpts)) {
					$this->SoapClient = new SoapClient(self::SOAP_URL, $this->SoapOpts);
				} else {
					$this->SoapClient = new SoapClient(self::SOAP_URL);
				}
			default :
				return $this->SoapClient;
		}
	}
}
?>