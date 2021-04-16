<?php

namespace storfollo\adtools\tests;

use PHPUnit\Framework\TestCase;
use storfollo\adtools;

class GroupTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    private $adtools;

    protected function setUp(): void
    {
        load_data::load_base_data();
        $config = require __DIR__ . '/domains.php';
        $this->adtools = adtools\adtools::connect_config($config['test']);
        load_data::load_test_data();
        load_data::load_group_test_data();
    }

    public function testGroup()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertInstanceOf(adtools\Group::class, $group);
    }

    public function testMembers()
    {
        $group = $this->adtools->group('CN=group,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $members = $group->members();
        $this->assertIsArray($members);
        $this->assertSame('cn=user2,ou=Users,ou=adtools-test,ou=Test,dc=example,dc=com', $members[0]);
    }
}
