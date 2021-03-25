<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 26.07.2019
 * Time: 13:32
 */

namespace storfollo\adtools\tests;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use storfollo\adtools;


class adtoolsTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    public $adtools;
    public static function setUpBeforeClass(): void
    {
        load_data::load_base_data();
        load_data::load_test_data();
    }
    public static function tearDownAfterClass(): void
    {
        load_data::delete();
    }

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->adtools=new adtools\adtools();
        $this->adtools->connect_and_bind('cn=admin,dc=example,dc=com', 'test', 'localhost');
    }

    public function testConnect()
    {
        set_include_path(__DIR__);
        $adtools=new adtools\adtools('test');
        $this->assertIsResource($adtools->ad);
    }

    public function testConnectInvalidFile()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Domain key invalid not found in config file');
        new adtools\adtools('invalid');
    }

    public function testInvalidConfig()
    {
        $this->expectExceptionMessage('DC and/or domain must be specified in config file');
        new adtools\adtools('missing_dc');
    }

    public function testConfig()
    {
        $adtools=new adtools\adtools('no_dc');
        $this->assertEquals('localhost', $adtools->config['dc']);
        $adtools=new adtools\adtools('no_domain');
        $this->assertEquals('localhost', $adtools->config['domain']);
    }

    public function testConnect_and_bind_no_username()
    {
        $adtools=new adtools\adtools();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username and/or password are not specified');
        $adtools->connect_and_bind(null, null);
    }

    public function testConnect_and_bind_invalid_chars()
    {
        $adtools=new adtools\adtools();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid characters in username or password');
        $adtools->connect_and_bind('u$er', 'æøå');
    }

    public function testConnect_and_bind_invalid_port()
    {
        $adtools=new adtools\adtools();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Port number must be numeric');
        $adtools->connect_and_bind('user', 'password', null, null, 'asdf');
    }


    public function testLdap_query()
    {
        $result = $this->adtools->ldap_query('(objectclass=*)', array('base_dn'=>'OU=Test,DC=example,DC=com', 'subtree'=>false));
        $this->assertEquals('ou=adtools-test,ou=Test,dc=example,dc=com', $result);
    }

    public function testMultipleHitsException()
    {
        $this->expectException(adtools\exceptions\MultipleHitsException::class);
        $this->adtools->ldap_query('(objectclass=user)', array('base_dn'=>'OU=Test,DC=example,DC=com', 'single_result'=>true));
    }

    public function testNoHitsException()
    {
        $this->expectException(adtools\exceptions\NoHitsException::class);
        $this->adtools->ldap_query('(objectclass=foo)', array('base_dn'=>'OU=Test,DC=example,DC=com'));
    }

    public function testNotConnected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not connected to AD');
        $adtools=new adtools\adtools();
        $adtools->ldap_query('');
    }

/*
    public function testQuery()
    {

    }

    public function testExtract_field()
    {

    }
*/

/*
    public function testLogin_form()
    {

    }*/
}
