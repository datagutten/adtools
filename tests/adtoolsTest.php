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
    /**
     * @var array
     */
    private $config;

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
        $this->config = require __DIR__.'/domains.php';
    }

    public function testInvalidConfig()
    {
        $this->expectExceptionMessage('DC must be specified in config file');
        adtools\adtools::connect_config($this->config['missing_dc']);
    }

    public function testConnect_and_bind_no_username()
    {
        $adtools=new adtools\adtools();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username and/or password are not specified');
        $adtools->connect_and_bind('', '', '');
    }

    public function testConnect_and_bind_invalid_chars()
    {
        $adtools=new adtools\adtools();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid characters in username or password');
        $adtools->connect_and_bind('u$er', 'æøå', 'localhost');
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
