<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 26.07.2019
 * Time: 13:32
 */

use datagutten\adtools\adtools;
use datagutten\adtools\LdapException;
use PHPUnit\Framework\TestCase;

class adtoolsTest extends TestCase
{
    /**
     * @var adtools
     */
    public $adtools;
    public function testLdap_query_escape()
    {
        $adtools=new adtools();
        $query = $adtools->ldap_query_escape('(foo=*)');
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

    public function testMove()
    {
        $this->adtools->move('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
        $result = $this->adtools->ldap_query('(displayName=user2)', array('base_dn'=>'ou=Test,dc=example,dc=com'));
        $this->assertEquals('cn=user2,ou=move,ou=adtools-test,ou=Test,dc=example,dc=com', $result['dn']);
    }
    public function testMoveAgain()
    {
        $this->expectException(LdapException::class);
        $this->adtools->move('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
    }
/*
    public function testQuery()
    {

    }

    public function testExtract_field()
    {

    }
*/
    public function testChange_password()
    {
        $this->markTestSkipped();
        $this->adtools->change_password('CN=user1,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'test2');
        $this->adtools->change_password('CN=user1,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'test2', true);
    }
/*
    public function testLogin_form()
    {

    }*/
}
