<?php
namespace storfollo\adtools;
use Exception;
use storfollo\adtools\exceptions\LdapException;
use storfollo\adtools\exceptions\MultipleHitsException;
use storfollo\adtools\exceptions\NoHitsException;

class adtools_groups extends adtools
{
	function __construct($domain)
	{
		parent::__construct($domain);
	}

    /**
     * Create a group
     * @param string $object_name
     * @param string $dn
     * @throws LdapException
     */
	function create_group($object_name,$dn)
	{
		$addgroup_ad['cn']="$object_name";
		$addgroup_ad['objectClass'][0]="top";
		$addgroup_ad['objectClass'][1]="group";
		$addgroup_ad['groupType']=0x80000002; //Security gorup
		//$addgroup_ad['member']=$members;
		$addgroup_ad["sAMAccountName"]=$object_name;

		ldap_add($this->ad,$dn,$addgroup_ad);
		
		if(ldap_error($this->ad) != "Success")
		  throw new LdapException($this->ad);
	}

    /**
     * Create a group if it not exists
     * @param string $group_name Group name
     * @param string $parent_ou Parent OU
     * @return string Group DN
     * @throws LdapException
     */
	function create_group_if_not_exists($group_name,$parent_ou)
	{
		$group_dn=sprintf('CN=%s,%s',$group_name,$parent_ou);
		$result=ldap_list($this->ad,$parent_ou,$q=sprintf('(&(objectClass=group)(cn=%s))',$group_name),array('cn'));
		$entries=ldap_get_entries($this->ad,$result);
		if($entries['count']==0)
		{
			if($this->create_group($group_name,$group_dn)===false)
			{
				throw new LdapException($this->ad);
			}
		}
		return $group_dn;
	}

    /**
     * Add a user to a group
     * @param string $user_dn
     * @param string $group_dn
     * @throws LdapException
     */
	function member_add($user_dn,$group_dn)
	{
		if(ldap_mod_add($this->ad,$group_dn,array('member'=>$user_dn))===false)
		{
            throw new LdapException($this->ad);
		}
	}

    /**
     * Remove a member from a group
     * @param string|array $user_dn User DN, set to empty array to remove all members from the group
     * @param string $group_dn Group DN
     * @throws Exception
     */
	function member_del($user_dn,$group_dn)
	{
		if(ldap_mod_del($this->ad,$group_dn,array('member'=>$user_dn))===false)
		{
			if(is_array($user_dn))
                throw new Exception(sprintf('Unable to remove all members from %s',$group_dn));
			else
                throw new Exception(sprintf('Unable to delete %s from %s',$user_dn,$group_dn));
		}
	}

    /**
     * Get members of a group
     * @param $group_dn
     * @return array Array with member DNs
     * @throws LdapException
     * @throws MultipleHitsException
     * @throws NoHitsException
     */
	function members($group_dn)
    {
        $members = $this->ldap_query(sprintf('(distinguishedName=%s)', $group_dn), array('attributes'=>array('member'), 'single_result'=>false));
        $members = $members[0]['member'];
        unset($members['count']);
        return $members;
    }

    /**
     * Check if a user is member of a group
     * @param string $group_dn
     * @param string $user_dn
     * @return bool
     * @throws LdapException
     * @throws MultipleHitsException
     * @throws NoHitsException
     */
    function has_member($group_dn, $user_dn)
    {
        $members = $this->members($group_dn);
        if(array_search($user_dn, $members)===false)
            return false;
        else
            return true;
    }
}