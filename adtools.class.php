<?php
class adtools
{
	public $ad=false;
	public $dn=false;
	function __construct($domain)
	{
		$this->connect($domain);
	}
	function ldap_query_escape($string)
	{
		return str_replace(array('\\','*','(',')',),array('\\00','\\2A','\\28','\\29'),$string);
	}
	function connect($domain)
	{
		//https://github.com/adldap/adLDAP/wiki/LDAP-over-SSL
		//http://serverfault.com/questions/136888/ssl-certifcate-request-s2003-dc-ca-dns-name-not-avaiable/705724#705724

		require 'domains.php';
		if(!isset($domains[$domain]))
			trigger_error("Invalid domain: $domain",E_USER_ERROR);
		$domain=$domains[$domain];
		if(isset($ldaps))
			$ad = ldap_connect("ldaps://".$domain['domain']) or trigger_error("Couldn't connect to AD!",E_USER_ERROR);
		else
			$ad = ldap_connect("ldap://".$domain['domain']) or trigger_error("Couldn't connect to AD!",E_USER_ERROR);

		ldap_set_option($ad, LDAP_OPT_PROTOCOL_VERSION, 3);
		if (!ldap_set_option($ad, LDAP_OPT_REFERRALS, 0))
			exit('Failed to set opt referrals to 0');

		$bd = ldap_bind($ad,$domain['username'],$domain['password']) or trigger_error("Couldn't bind to AD!",E_USER_ERROR);
		$this->ad=$ad;
		$this->dn=$domain['dn'];
	}
	
	function find_object($name,$base_dn=false,$type='user',$return_all_info=false)
	{
		if($base_dn===false)
			$base_dn=$this->dn;

		if($type=='user')
			$result=ldap_search($this->ad,$base_dn,$q="(displayName=$name)",array('sAMAccountName'));
		elseif($type=='computer')
			$result=ldap_search($this->ad,$base_dn,$q="(name=$name)",array('name'));
		else
			return false;
		$entries=ldap_get_entries($this->ad,$result);

		if($entries['count']>1)
		{
			echo "Multiple hits for $name\n";
			return false;
		}
		if($entries['count']==0)
		{
			echo "No hits for query $q in $base_dn\n";
			return false;
		}
		if($return_all_info===false)
			return $entries[0]['dn'];
		else
			return $entries[0];
	}
	
	//-----------old---------------

	//http://www.morecavalier.com/index.php?whom=Apps%2FLDAP+timestamp+converter
	function convert_AD_date ($ad_date) {
	
		if ($ad_date == 0) {
			return '0000-00-00';
		}
	
		$secsAfterADEpoch = $ad_date / (10000000);
		$AD2Unix=((1970-1601) * 365 - 3 + round((1970-1601)/4) ) * 86400;
	
		// Why -3 ?
		// "If the year is the last year of a century, eg. 1700, 1800, 1900, 2000,
		// then it is only a leap year if it is exactly divisible by 400.
		// Therefore, 1900 wasn't a leap year but 2000 was."
	
		$unixTimeStamp=intval($secsAfterADEpoch-$AD2Unix);
		$myDate = date("Y-m-d H:i:s", $unixTimeStamp); // formatted date
	
		return $myDate;
	}
	function unix_timestamp_to_microsoft($unix_timestamp)
	{
		$microsoft=$unix_timestamp+11644473600;
		$microsoft=$microsoft.'0000000';
		$microsoft=number_format($microsoft, 0, '', '');
		return $microsoft;
	}

	function extract_field($objects,$field)
	{
		foreach($objects as $key=>$object)
		{
			$extract[$key]=$object[$field][0];
		}
		return $extract;
	}
	//http://www.youngtechleads.com/how-to-modify-active-directory-passwords-through-php/
	function pwd_encryption( $newPassword ) {
		$newPassword = "\"" . $newPassword . "\"";
		$len = strlen( $newPassword );
		$newPassw = "";
		for ( $i = 0; $i < $len; $i++ ){
			$newPassw .= "{$newPassword{$i}}\000";
		}
		$userdata["unicodePwd"] = $newPassw;
		return $userdata;
	}
	function change_passord($dn,$password) //Reset password for user
	{
		return ldap_mod_replace($this->ad,$dn,$this->pwd_encryption($password));
	}
	function dsmod_password($dn,$password)
	{
		return "dsmod user \"{$dn}\" -pwd $password -mustchpwd yes -pwdneverexpires no\r\n";
	}
	function __destruct()
	{
	    ldap_unbind($this->ad);
	}
}
?>