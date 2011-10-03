<?php

require_once dirname(__DIR__) . '/library/webservices/soap/salesnet/soapproxy.class.php';

require_once dirname(__DIR__) . '/library/webservices/soap/salesnet/login.class.php';

require_once 'PHPUnit/Autoload.php';

use WebServices\Soap\SalesNet\SoapProxy;
use WebServices\Soap\SalesNet\Login;
/**
 * SoapProxy test case.
 */
class SoapProxyTest extends PHPUnit_Framework_TestCase {
	
	/**
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
		$login = $this->getMock('WebServices\Soap\SalesNet\Login', array(), array(), '', FALSE);
		$this->SoapProxy->__construct(SoapProxy::CAMPAIGNS, $login);
		$this->assertAttributeSame(SoapProxy::CAMPAIGNS, 'SoapUrl', $this->SoapProxy);
		$this->assertAttributeSame($login, 'Login', $this->SoapProxy);
	}
	
	/**
	 * Tests that calls to undefined methods are proxied through to the soap client object held in the object.
	 * @dataProvider SoapCallDataProvider
	 */
	public function testProxyingToSoapClient($url, $method, array $args = NULL) {
		$login = $this->getMock('WebServices\Soap\SalesNet\Login', array(), array(), '', FALSE);
		$login->expects($this->once())->method('doLogin')->will($this->returnValue(new SoapHeader('http://www.salesnet.com/wsapi/', 'Authentication', array ('token' => 'foobar'))));
		$this->SoapProxy->setSoapUrl($url);
		$this->SoapProxy->setLogin($login);
		try {
			if ($args) {
				$this->SoapProxy->$method($args);
			} else {
				$this->SoapProxy->$method();
			}
		} catch (BadMethodCallException $e) {
			$this->assertInstanceOf('SoapFault', $e->getPrevious());
		}
	}
	
	/**
	 * Tests SoapProxy->__sleep()
	 */
	public function test__sleep() {
		$sleep = serialize($this->SoapProxy);
		$this->assertEquals($this->SoapProxy, unserialize($sleep));
	}
	
	/**
	 * Tests SoapProxy->setSoapOpts()
	 * @dataProvider SoapOptsProvider
	 */
	public function testSetSoapOpts(array $soapOpts) {
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setSoapOpts($soapOpts));
		$this->assertAttributeSame($soapOpts, 'SoapOpts', $this->SoapProxy);	
	}
	
	/**
	 * Tests SoapProxy->setLogin()
	 */
	public function testSetLogin() {
		$login = $this->getMock('WebServices\Soap\SalesNet\Login', array(), array(), '', FALSE);
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setLogin($login));
		$this->assertAttributeSame($login, 'Login', $this->SoapProxy);
	}
	
	/**
	 * Tests SoapProxy->setSoapUrl()
	 * @dataProvider ValidSoapUrlsProvider
	 */
	public function testSetSoapUrl($url) {
		$this->assertSame($this->SoapProxy, $this->SoapProxy->setSoapUrl($url));
		$this->assertAttributeSame($url, 'SoapUrl', $this->SoapProxy);
	}
	/**
	 * 
	 * @dataProvider InvalidUrlsProvider
	 */
	public function testInvalidUrlRaisesException($url) {
		$this->setExpectedException('InvalidArgumentException', sprintf('Invalid url "%s" passed in WebServices\Soap\SalesNet\SoapProxy::setSoapUrl', $url));
		$this->SoapProxy->setSoapUrl($url);
	}
	
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
		 * Use reflection to grab all of the url constants from the class.
		 * Then set them in an array to return as arguments.
		 * This will protect against any changes in the future to the soap endpoints.
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
}

