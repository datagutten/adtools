<?php
namespace storfollo\adtools\tests;

use PHPUnit\Framework\TestCase;
use storfollo\adtools;
use Symfony\Component\Ldap\Exception\LdapException;


class adtoolsWriteTest extends TestCase
{
    /**
     * @var adtools\adtools
     */
    public $adtools;
    public static function setUpBeforeClass(): void
    {
        load_data::load_base_data();
    }

    public function setUp(): void
    {
        $config = require __DIR__.'/domains.php';
        $this->adtools=adtools\adtools::connect_config($config['test']);
        load_data::load_test_data();
    }

    /**
     * @throws adtools\exceptions\LdapException
     * @throws adtools\exceptions\MultipleHitsException
     * @throws adtools\exceptions\NoHitsException
     */
    public function testMove()
    {
        $user = $this->adtools->user('CN=user2,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $new = $user->move('OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
        $this->assertEquals('CN=user2,OU=move,OU=adtools-test,OU=Test,DC=example,DC=com', $new);
        $result = $this->adtools->ldap_query('(displayName=user2)', array('base_dn'=>'ou=Test,dc=example,dc=com'));
        $this->assertEquals('cn=user2,ou=move,ou=adtools-test,ou=Test,dc=example,dc=com', $result);
    }

    public function testMoveNonExisting()
    {
        $this->expectException(LdapException::class);
        $user = $this->adtools->user('CN=user3,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $user->move('OU=move,OU=adtools-test,OU=Test,DC=example,DC=com');
    }

    /**
     * @throws adtools\exceptions\LdapException
     */
    public function testChange_password()
    {
        $user = $this->adtools->user('CN=user1,OU=Users,OU=adtools-test,OU=Test,DC=example,DC=com');
        $user->change_password('test2');
        $user->change_password( 'test2', true);
        $result = $this->adtools->find_object('user1', 'OU=adtools-test,OU=Test,DC=example,DC=com','user', array('pwdLastSet'));
        $this->assertEquals('0', $result);
    }

    public function tearDown(): void
    {
        load_data::delete('OU=adtools-test,OU=Test,DC=example,DC=com');
    }
}
