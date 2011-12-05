<?php

require_once dirname(__DIR__) . '/library/webservices/soap/salesnet/soapproxy.class.php';

require_once dirname(__DIR__) . '/library/webservices/soap/salesnet/authentication.class.php';

require_once 'PHPUnit/Autoload.php';

use WebServices\Soap\SalesNet\SoapProxy;
use WebServices\Soap\SalesNet\Authentication;
/**
 * Tests for the Soap Proxy class for working with the SalesNet API.
 * @author Jeremy Cook
 */
class SoapProxyTest extends PHPUnit_Framework_TestCase {
	
	/**
	 * SoapProxy fixture for the tests.
	 * @var SoapProxy
	 */
	private $SoapProxy;
	
	/**
	 * Prepares the environment before running a test.
	 */
	protected function setUp() {
		parent::setUp();
		$this->SoapProxy = new SoapProxy();
	}
	
	/**
	 * Cleans up the environment after running a test.
	 */
	protected function tearDown() {
		$this->SoapProxy = null;		
		parent::tearDown();
	}
	
	/**
	 * Tests SoapProxy->__construct()
	 */
	public function test__construct() {
		$auth = $this->getMock('WebServices\Soap\SalesNet\Authentication', array(), array(), '', FALSE);
		$this->SoapProxy->__construct(SoapProxy::CAMPAIGNS, $auth);
		$this->assertAttributeSame(SoapProxy::CAMPAIGNS, 'SoapUrl', $this->SoapProxy);
		$this->assertAttributeSame($auth, 'Auth', $this->SoapProxy);
	}
	
	/**
	 * Tests that calls to undefined methods are proxied through to the soap client property held in the object using PHP's magic __call method.
	 * These are then called as methods of the SalesNet API
	 * 
	 * @dataProvider SoapCallDataProvider
	 */
	public function testProxyingToSoapClient($url, $method, array $args = array()) {
		$auth = $this->getMock('WebServices\Soap\SalesNet\Authentication', array(), array(), '', FALSE);
		$auth->expects($this->once())->method('doLogin')->will($this->returnValue(new SoapHeader('http://www.salesnet.com/wsapi/', 'Authentication', array ('token' => 'foobar'))));
		$this->SoapProxy->setSoapUrl($url);
		$this->SoapProxy->setAuthentication($auth);
		try {
			$this->SoapProxy->$method($args);
		} catch (BadMethodCallException $e) {
			$this->assertInstanceOf('SoapFault', $e->getPrevious());
		}
	}
	/**
	 * Tests that calling a soap method before specifying which SalesNet endpoint to use raises an exception.
	 * 
	 * @dataProvider InvalidSoapMethodNamesProvider
	 */
	public function testCallingSoapMethodWithNoURLSetRaisesException($method) {
		$this->setExpectedException('BadMethodCallException', sprintf('SoapUrl must be set before calling %s in WebServices\Soap\SalesNet\SoapProxy::__call', $method));
		$this->SoapProxy->$method();
	}
	/**
	 * Tests that calling a soap method before passing in an authentication object raises an exception.
	 * 
	 * @dataProvider InvalidSoapMethodNamesProvider
	 */
	public function testCallingSoapMethodWithNoLoginSetRaisesException($method) {
		$this->setExpectedException('BadMethodCallException', sprintf('Authentication object must be set before calling %s in WebServices\Soap\SalesNet\SoapProxy::__call', $method));
		$this->SoapProxy->setSoapUrl(SoapProxy::ACTIVITY);
		$this->SoapProxy->$method();
	}
	
	/**
	 * Tests the serialization of the object.
	 */
	public function test__sleep() {
		$sleep = serialize($this->SoapProxy);
		$this->assertEquals($this->SoapProxy, unserialize($sleep));
	}
	
	/**
	 * Tests setting an array of soap options.
	 * 
	 * @dataProvider SoapOptsProvider
	 */
	public function testSetSoapOpts(array $soapOpts) {
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setSoapOpts($soapOpts));
		$this->assertAttributeSame($soapOpts, 'SoapOpts', $this->SoapProxy);	
	}
	
	/**
	 * Tests setting the authentication object
	 */
	public function testSetAuthentication() {
		$auth = $this->getMock('WebServices\Soap\SalesNet\Authentication', array(), array(), '', FALSE);
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setAuthentication($auth));
		$this->assertAttributeSame($auth, 'Auth', $this->SoapProxy);
	}
	
	/**
	 * Tests setting the SOAP endpoing for the various SalesNet services.
	 * 
	 * @dataProvider ValidSoapUrlsProvider
	 */
	public function testSetSoapUrl($url) {
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setSoapUrl($url));
		$this->assertAttributeSame($url, 'SoapUrl', $this->SoapProxy);
	}
	/**
	 * Tests that passing an invalid URL as a SalesNet endpoint raises an exception.
	 * 
	 * @dataProvider InvalidUrlsProvider
	 */
	public function testInvalidUrlRaisesException($url) {
		$this->setExpectedException('InvalidArgumentException', sprintf('Invalid url "%s" passed in WebServices\Soap\SalesNet\SoapProxy::setSoapUrl', $url));
		$this->SoapProxy->setSoapUrl($url);
	}
	/**
	 * 
	 * Data Providers
	 */
	
	static public function SoapOptsProvider() {
		return array(
			array(array('compression' => TRUE)),
			array(array('trace' => TRUE)),
			array(array('foo' => 'bar')),
			array(array())
		);
	}
	
	static public function ValidSoapUrlsProvider() {
		$ret = array();
		/**
		 * 
		 * Use reflection to grab all of the url constants from the class then set them in an array to return.
		 * This will protect against any changes in the future to the soap endpoints and is easier than listing all of the url's here.
		 */
		$reflect = new ReflectionClass('WebServices\Soap\SalesNet\SoapProxy');
		foreach ($reflect->getConstants() as $url) {
			$ret[][] = $url;
		}
		return $ret;
	}
	
	static public function InvalidUrlsProvider() {
		return array(
			array('http://www.bbc.com/'),
			array('foo'),
			array(TRUE),
			array(NULL),
			array(1)
		);
	}
	
	static public function SoapCallDataProvider() {
		return array(
			array(SoapProxy::ACTIVITY, 'foobar'),
			array(SoapProxy::CAMPAIGNS, 'barbaz', array('bar' => 'baz'))
		);
	}
	
	static public function InvalidSoapMethodNamesProvider() {
		return array(
			array('foo'),
			array('bar'),
			array('baz')
		);
	}
}

