<?php
require_once 'PHPUnit/Autoload.php';

require_once dirname(__DIR__) . '/library/webservices/soap/salesnet/login.class.php';

use WebServices\Soap\SalesNet\Login;
/**
 * Test case for the SalesNetLogin class
 * 
 * @author Jeremy Cook
 * @version 1.0
 */
class SalesNetLoginTest extends PHPUnit_Framework_TestCase {
	/**
	 * Fixture instance of Login
	 * 
	 * @var Login
	 */
	protected $login;
	/**
	 * Method to create the fixture for each test run
	 * 
	 * @return void
	 */
	protected function setUp() {
		$this->login = new Login();
	}
	/**
	 * 
	 * Method to test the constructor of the object
	 * @param int $expiryString
	 * @dataProvider ValidExpiryHours
	 */
	public function testConstruct($expiryHours) {
		$login = new Login($expiryHours);
		$this->assertAttributeEquals(sprintf('+%s hours', $expiryHours), 'TokenExpiryString', $login);
	}
	/**
	 * 
	 * Tests that an invalid number of hours passed to the constructor raises an exception
	 * @param mixed $expiryHours
	 * @dataProvider InvalidExpiryHours
	 */
	public function testBadExpiryHoursRaisesException($expiryHours) {
		$this->setExpectedException('InvalidArgumentException', sprintf('The number of hours for the token to be valid must be between 1 and 12, "%s" passed in WebServices\Soap\SalesNet\Login::__construct', $expiryHours));
		$login = new Login($expiryHours);
	}
	/**
	 * Method to test that setting the company login works
	 * 
	 * @dataProvider TestCredentialsData
	 */
	public function testCompanyLogin($company) {
		$this->assertSame($this->login, $this->login->setCompanyLogin($company));
		$this->assertSame($company, $this->login->getCompanyLogin());
	}
	/**
	 * Method to test that setting the user login works.
	 * 
	 * @dataProvider TestCredentialsData
	 */
	public function testUserLogin($user) {
		$this->assertSame($this->login, $this->login->setUserName($user));
		$this->assertSame($user, $this->login->getUserName());
	}
	/**
	 * Method to assert that setting the user password works
	 * 
	 * @dataProvider TestCredentialsData
	 */
	public function testPassword($password) {
		$this->assertSame($this->login, $this->login->setPassword($password));
		$this->assertSame($password, $this->login->getPassword());
	}
	/**
	 * Test method for performing a login.
	 * This test method uses real SalesNet credentials to test that the login works.
	 * 
	 * @dataProvider ValidCredentials
	 */
	public function testLogin($company, $user, $password, array $opts = NULL) {
		if ($opts)
			$this->login->setSoapOpts($opts);
		$this->login->setCompanyLogin($company)->setUserName($user)->setPassword($password);
		$this->assertInstanceOf('SoapHeader', $this->login->doLogin());
	}
	
	/**
	 * Test method for asserting that the SoapHeader object returned from logging in is stored and used on subsequent requests.
	 * It checks that two subsequent calls to doLogin returns the same SoapHeader instance.
	 * @dataProvider ValidCredentials
	 */
	public function testHeaderObjectsSame($company, $user, $password) {
		$this->login->setCompanyLogin($company)->setUserName($user)->setPassword($password);
		$this->assertSame($this->login->doLogin(), $this->login->doLogin());
	}
	/**
	 * Test method for asserting that the SoapHeader object returned from logging in is stored and used on subsequent requests.
	 * 
	 * @dataProvider ValidCredentials
	 */
	public function testExpiryTime($company, $user, $password) {
		$this->login->setCompanyLogin($company)->setUserName($user)->setPassword($password)->doLogin();
		$this->assertInstanceOf('DateTime', $this->login->getExpiryTime());
		$date = new DateTime('+12 hours', new DateTimeZone('UTC'));
		$this->assertSame($date->format('G'), $this->login->getExpiryTime()->format('G'));
	}
	/**
	 * Tests setting an expiry time manually
	 * @dataProvider ExpiryTimes
	 */
	public function testSetExpiryTime(DateTime $time) {
		$this->login->setExpiryTime($time);
		$this->assertSame($time, $this->login->getExpiryTime());
	}
	/**
	 * Tests that setting a new expiry time that has already past causes a new SoapHeader (and security token) to be set.
	 * @dataProvider ValidCredentials
	 */
	public function testNewExpiryTimeCausesNewSoapHeader($company, $user, $password) {
		$header = $this->login->setCompanyLogin($company)->setUserName($user)->setPassword($password)->doLogin();
		$this->login->setExpiryTime(new DateTime('-1 week', new DateTimeZone('UTC')));
		$this->assertThat($this->login->doLogin(), $this->logicalNot($this->identicalTo($header)));
	}
	/**
	 * Method to assert that attempting a login with no credentials raises an exception
	 * 
	 * @expectedException \BadMethodCallException
	 */
	public function testLoginNoData() {
		$this->login->doLogin();
	}
	/**
	 * 
	 * Test method for the serialization functionality
	 * Asserts that when the object is serialized and unserialized it is equal to the unserialized instance
	 */
	public function testSerialize() {
		$obj = serialize($this->login);
		$this->assertEquals($this->login, unserialize($obj));
	}
	/**
	 * 
	 * Tests that the reset method causes all properties available through getters to be reset to null
	 */
	public function testReset() {
		$this->assertSame($this->login, $this->login->reset());
		$reflect = new ReflectionObject($this->login);
		foreach ($reflect->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if (substr($method, 0, 3) === 'get') {
				$this->assertNull($this->login->$method());
			}
		}
	}
	/**
	 * Tests setting and retrieving soap options from the object.
	 * @dataProvider SoapOpts
	 */
	public function testSetSoapOpts(array $opts) {
		$this->assertSame($this->login, $this->login->setSoapOpts($opts));
		$this->assertSame($opts, $this->login->getSoapOpts());
	}
	/**
	 * Method to assert that a bad login results in a SoapFault.
	 * 
	 */
	public function testBadLogin() {
		$this->setExpectedException('SoapFault', 'An Error has occurred.');
		$this->login->setCompanyLogin('foo')->setUserName('bar')->setPassword('baz')->doLogin();
	}
	/**
	 * Assert that a non-string value passed to setCompanyLogin results in an exception
	 * 
	 * @dataProvider BadCredentialData
	 */
	public function testBadCompany($company) {
		$this->setExpectedException('InvalidArgumentException', sprintf('Argument "%s" passed to WebServices\Soap\SalesNet\Login::setCompanyLogin must be a string', $company));
		$this->login->setCompanyLogin($company);
	}
	/**
	 * Assert that a non-string value passed to setUserName results in an exception
	 * 
	 * @dataProvider BadCredentialData
	 */
	public function testBadUser($user) {
		$this->setExpectedException('InvalidArgumentException', sprintf('Argument "%s" passed to WebServices\Soap\SalesNet\Login::setUserName must be a string', $user));
		$this->login->setUserName($user);
	}
	/**
	 * Assert that a non-string value passed to setPassword results in an exception
	 * 
	 * @dataProvider BadCredentialData
	 */
	public function testBadPassword($password) {
		$this->setExpectedException('InvalidArgumentException', sprintf('Argument "%s" passed to WebServices\Soap\SalesNet\Login::setPassword must be a string', $password));
		$this->login->setPassword($password);
	}
	/**
	 * 
	 * Data Providers
	 */
	
	static public function TestCredentialsData() {
		return array(
			array('foo'), 
			array('bar'), 
			array ('baz')
		);
	}
	/**
	 * 
	 * ENTER YOUR OWN SALESNET CREDENTIALS HERE TO RUN A SUCCESSFUL TEST
	 */
	static public function ValidCredentials() {
		return array(
			array('COMPANY_NAME', 'USERNAME', 'PASSWORD', array('trace' => TRUE, 'exceptions' => FALSE)),
			array('COMPANY_NAME', 'USERNAME', 'PASSWORD', array())
		);
	}
	
	static public function BadCredentialData() {
		return array(
			array (5), 
			array (3.7), 
			array(array ('foo', 'bar'))
		);
	}
	
	static public function ExpiryTimes() {
		$zone = new DateTimeZone('UTC');
		return array(
			array(new DateTime('now', $zone)),
			array(new DateTime('+1 hour', $zone)),
			array(new DateTime('last week', $zone))
		);
	}
	
	static public function SoapOpts() {
		return array(
			array(array('trace' => TRUE, 'exceptions' => FALSE)),
			array(array('foo')),
			array(array('bar'))
		);
	}
	
	static public function ValidExpiryHours() {
		return array(
			array(1),
			array(2),
			array(3),
			array(4),
			array(5),
			array(6),
			array(7),
			array(8),
			array(9),
			array(10),
			array(11),
			array(12),
		);
	}
	
	static public function InvalidExpiryHours() {
		return array(
			array(0),
			array(1.5),
			array(13),
			array('foo')
		);
	}
}
?>
