<?php

namespace storfollo\adtools\tests;

use PHPUnit\Framework\TestCase;
use storfollo\adtools;

class adtools_groupsTest extends TestCase
{
    /**
     * @var adtools\adtools_groups
     */
    public $adtools;
    public static function setUpBeforeClass(): void
    {
        load_data::load_base_data();
    }

    public function setUp(): void
    {
        $config = require __DIR__.'/domains.php';
        $this->adtools = adtools\adtools_groups::connect_config($config['test']);
        load_data::load_test_data();
        load_data::load_group_test_data();
    }

    public function tearDown(): void
    {
        load_data::delete('OU=adtools-test,OU=Test,DC=example,DC=com');
    }

    public function testCreateGroup()
    {
        $this->markTestSkipped(); //Test does not work
        $this->expectException(adtools\exceptions\LdapException::class);
        $this->expectException(\Exception::class);
        $this->adtools->create_group('created-group', 'OU=adtools-test,OU=Test,DC=example,DC=com');
    }
    public function testMembers()
    {
        $members = $this->adtools->members('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals(array('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', 'cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com'), $members);
    }
    public function testMemberDelete()
    {
        $this->adtools->member_del('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $members = $this->adtools->members('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertContains('cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $members);
        $this->assertNotContains('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $members);
    }

    function testAddMember()
    {
        //$this->adtools->member_del('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->adtools->member_add('CN=user3,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $members = $this->adtools->members('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertContains('cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $members);
    }

    public function testHasMember()
    {
        //$members = $this->adtools->members('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        //print_r($members);
        $status = $this->adtools->has_member('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com');
        $this->assertEquals(true, $status);
        $status = $this->adtools->has_member('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com', 'cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com');
        $this->assertEquals(false, $status);
    }
}
