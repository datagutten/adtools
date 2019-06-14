<?php
/**
 * Created by PhpStorm.
 * User: abi
 * Date: 14.06.2019
 * Time: 12:59
 */

/**
 * @param string $locale Locale
 * @param string $text_domain Text domain
 */
function set_locale($locale,$text_domain)
{
    $locale_path=dirname(__FILE__).'/locale';
    if(!file_exists($file=$locale_path."/$locale/LC_MESSAGES/$text_domain.mo"))
    {
        throw new InvalidArgumentException(sprintf(_("No translation found for locale %s. It should be placed in %s"),$locale,$file));
    }
    putenv('LC_MESSAGES='.$locale);
    if (defined('LC_MESSAGES')) {
        setlocale(LC_MESSAGES, $locale); // Linux
        bindtextdomain("messages", $locale_path);
    } else {
        putenv('LC_MESSAGES='.$locale); // Windows
        bindtextdomain($text_domain, $locale_path);
    }
    textdomain($text_domain);
}