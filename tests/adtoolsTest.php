<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 26.07.2019
 * Time: 13:32
 */

namespace datagutten\adtools\tests;

use datagutten\adtools\adtools;
use datagutten\adtools\adtools_utils;
use PHPUnit\Framework\TestCase;


class adtoolsTest extends TestCase
{
    /**
     * @var adtools
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

    public function testLdap_query_escape()
    {
        $query = adtools_utils::ldap_query_escape('(foo=*)');
        $this->assertEquals('\\28foo=\\2A\\29', $query);
    }

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->adtools=new adtools();
        $this->adtools->connect_and_bind('localhost', 'cn=admin,dc=example,dc=com', 'test', 'ldap', '389', 'localhost');
    }

    public function testConnect()
    {
        set_include_path(__DIR__);
        $adtools=new adtools('test');
        $this->assertIsResource($adtools->ad);
    }

    public function testConnect_and_bind()
    {
        $this->assertIsResource($this->adtools->ad);
    }

    public function testLdap_query()
    {
        $result = $this->adtools->ldap_query('(objectclass=*)', array('base_dn'=>'OU=Test,DC=example,DC=com', 'subtree'=>false));
        $this->assertEquals('ou=adtools-test,ou=Test,dc=example,dc=com', $result['dn']);
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
