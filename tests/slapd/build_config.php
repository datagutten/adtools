<?php
$template = file_get_contents(__DIR__.'/slapd.conf.dist');
$schema_path = realpath(__DIR__.'/schema');
$config = str_replace('__SCHEMADIR__', $schema_path, $template);
file_put_contents(__DIR__.'/slapd.conf', $config);
