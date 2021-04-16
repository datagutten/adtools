<?php


namespace storfollo\adtools;

use Countable;
use Symfony\Component\Ldap;

class Group extends Entry implements Countable
{
    /**
     * Group constructor.
     * @param $ldap
     * @param $dn
     */
    function __construct(Ldap\Ldap $ldap, string $dn)
    {
        parent::__construct($ldap, $dn, '(objectClass=group)');
    }

    public static function create(Ldap\Ldap $ldap, $name, $ou, $extra_attributes = []): Group
    {
        $attributes['cn'] = $name;
        $attributes['objectClass'][0] = "top";
        $attributes['objectClass'][1] = "group";

        $attributes['groupType'] = 0x80000002; //Security group
        $attributes['instanceType'] = 4;
        $attributes["sAMAccountName"] = $name;

        $attributes = array_merge($attributes, $extra_attributes);

        $dn = sprintf('CN=%s,%s', $name, $ou);

        return self::add($ldap, $dn, $attributes);
    }

    /**
     * Get group members
     * @return array
     */
    function members(): array
    {
        return $this->entry->getAttribute('member') ?? [];
    }

    function members_recursive(): array
    {
        $members = [];
        foreach ($this->members() as $member)
        {
            //$this->ldap_query(sprintf('(&(distinguishedName=%s)(objectClass=group))', $member), array('attributes' => array('objectClass'), 'single_result' => true));
            $result = $this->ldap->query($member, '(objectClass=group)')->execute();

            if ($result->count() == 0)
                $members[] = $member;
            else
            {
                $sub_group = new Group($this->ldap, $member);
                $members = array_merge($members, $sub_group->members_recursive());
            }
        }
        return $members;
    }

    /**
     * Delete group member
     * @param array $user_dn
     */
    function members_delete(array $user_dn)
    {
        $this->entryManager->removeAttributeValues($this->entry, 'member', $user_dn);
        $members = $this->members();
        if ($user_dn === [])
            $this->entry->removeAttribute('member');
        else
        {
            foreach ($user_dn as $user)
            {
                $key = array_search($user, $members);
                unset($members[$key]);
            }
            $this->entry->setAttribute('member', $members);
        }
    }

    function member_delete(string $user_dn)
    {
        $this->members_delete([$user_dn]);
    }

    /**
     * Add multiple members to the group
     * @param array $user_dn
     */
    function members_add(array $user_dn)
    {
        $this->entryManager->addAttributeValues($this->entry, 'member', $user_dn);
        $members = $this->members();
        foreach ($user_dn as $user)
        {
            $members[] = $user;
        }
        $this->entry->setAttribute('member', $members);
    }

    function member_add(string $user_dn)
    {
        $this->members_add([$user_dn]);
    }

    function has_member(string $user_dn): bool
    {
        return array_search($user_dn, $this->members()) !== false;
    }

    public function count(): int
    {
        return count($this->members());
    }
}