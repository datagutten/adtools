<?Php
$domains['test']=array('domain'=>'localhost','dc'=>'localhost' ,'dn'=>'OU=Test,DC=example,DC=com','username'=>'cn=admin,dc=example,dc=com','password'=>'test');
$domains['missing_dc']=array();
$domains['no_dc']=array('domain'=>'localhost');
$domains['no_domain']=array('dc'=>'localhost');

return $domains;