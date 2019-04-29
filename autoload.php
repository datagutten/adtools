<?php
/**
 * Created by PhpStorm.
 * User: Anders
 * Date: 29.04.2019
 * Time: 18.32
 */
spl_autoload_register(function ($class_name) {
     if(file_exists(__DIR__.'/'.$class_name.'.php'))
        /** @noinspection PhpIncludeInspection */
        include __DIR__.'/'.$class_name.'.php';
});

