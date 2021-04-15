<?php

namespace storfollo\adtools\tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use storfollo\adtools\adtools_utils;

class adtools_utilsTest extends TestCase
{

    public function testOu_name()
    {
        $ou = adtools_utils::ou_name('OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals('Users', $ou);
    }

    public function testPwd_encryption()
    {
        $pwd = adtools_utils::pwd_encryption('test');
        $this->assertEquals("\"\000t\000e\000s\000t\000\"\000", $pwd);
    }

    public function testDsmod_password()
    {
        $cmd = adtools_utils::dsmod_password('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'test');
        $this->assertEquals("dsmod user \"CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com\" -pwd test -mustchpwd no -pwdneverexpires no\r\n", $cmd);
    }

    public function testFindFlags()
    {
        $flags = adtools_utils::findFlags(80);
        $this->assertEquals([16=>'LOCKOUT', 64=>'PASSWD_CANT_CHANGE'], $flags);
    }

    public function testLdap_query_escape()
    {
        $query = adtools_utils::ldap_query_escape('(foo=*)');
        $this->assertEquals('\\28foo=\\2A\\29', $query);
    }

    public function testOu()
    {
        $ou = adtools_utils::ou('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals('OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', $ou);
    }

    public function testUnix_timestamp_to_microsoft()
    {
        $time = adtools_utils::unix_timestamp_to_microsoft(1564231012);
        $this->assertEquals(132087046120000000, $time);
    }

    public function testMicrosoft_timestamp_to_unix()
    {
        $time = adtools_utils::microsoft_timestamp_to_unix(132087046120000000);
        $this->assertEquals(1564231012, $time);
        $time = adtools_utils::microsoft_timestamp_to_unix(0);
        $this->assertEquals('0000-00-00', $time);
    }

    public function testField_name()
    {
        $this->assertEquals('Display Name', adtools_utils::field_name('displayName'));
        $this->expectException(InvalidArgumentException::class);
        adtools_utils::field_name('invalid');
    }

/*    public function testExtract_field()
    {
        //$data = ['results'=>['field1'=>]]
    }*/
}
