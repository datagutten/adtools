<?php

namespace datagutten\adtools\tests;

use datagutten\adtools\adtools_utils;
use PHPUnit\Framework\TestCase;

class adtools_utilsTest extends TestCase
{

    public function testOu_name()
    {

    }

    public function testPwd_encryption()
    {
        $pwd = adtools_utils::pwd_encryption('test');
        $this->assertEquals("\"\000t\000e\000s\000t\000\"\000", $pwd);
    }

    public function testDsmod_password()
    {

    }

    public function testUnix_timestamp_to_microsoft()
    {

    }

    public function testFindFlags()
    {

    }

    public function testLdap_query_escape()
    {
        $query = adtools_utils::ldap_query_escape('(foo=*)');
        $this->assertEquals('\\28foo=\\2A\\29', $query);
    }

    public function testOu()
    {

    }

    public function testMicrosoft_timestamp_to_unix()
    {

    }

    public function testField_names()
    {

    }

    public function testExtract_field()
    {

    }
}
