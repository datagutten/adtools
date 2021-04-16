<?php

namespace storfollo\adtools\tests;

use PHPUnit\Framework\TestCase;
use storfollo\adtools;
use Symfony\Component\Process\Exception\ProcessFailedException;

class adtools_groupsTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    public $adtools;

    public function setUp(): void
    {
        load_data::load_base_data();
        $config = require __DIR__.'/domains.php';
        $this->adtools = adtools\adtools::connect_config($config['test']);
        try
        {
            load_data::delete('OU=adtools-test,OU=Test,DC=example,DC=com');
        } catch (ProcessFailedException $e) {}
        load_data::load_test_data();
        load_data::load_group_test_data();
    }

    public function testCreateGroup()
    {
        //Extra attributes to make creation work with LDAP emulator
        $attributes['objectCategory'] = 'CN=Group,CN=Schema,CN=Configuration,DC=example,DC=com';
        $attributes['sAMAccountType'] = 268435456;
        $attributes['objectClass'][0] = 'mstop';
        $attributes['objectClass'][1] = 'customActiveDirectoryGroup';
        $attributes['objectSID'] =  sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));

        $group = adtools\Group::create($this->adtools->ldap, 'created-group', 'OU=adtools-test,OU=Test,DC=example,DC=com', $attributes);
        $this->assertInstanceOf(adtools\Group::class, $group);
        $group->member_add('CN=user3,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertTrue($group->has_member('CN=user3,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com'));
    }

    public function testMembers()
    {
        $members = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com')->members();
        $this->assertEquals(array('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', 'cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com'), $members);
    }

    public function testMembersRecursive()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $group->member_add('CN=group2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals(array('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', 'cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', 'cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com'), $group->members_recursive());
    }


    public function testMemberDelete()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $group->members_delete(['cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com']);
        $this->assertContains('cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $group->members());
        $this->assertNotContains('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $group->members());
    }

    function testAddMember()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $group->member_add('cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com');
        $this->assertContains('cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $group->members());
        //Check if the change was written to AD
        $group_check = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertContains('cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $group_check->members());
    }

    public function testHasMember()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $status = $group->has_member('cn=user1,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com');
        $this->assertEquals(true, $status);
        $status = $group->has_member( 'cn=user3,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com');
        $this->assertEquals(false, $status);
    }
}
