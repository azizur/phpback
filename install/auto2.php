<?php
/**
 * First step of setup: database creation (action of index.php)
 * @copyright  Copyright (c) 2014 PHPBack
 * @author       Ivan Diaz <ivan@phpback.org>
 * @license      http://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link            https://github.com/ivandiazwm/phpback
 * @since         1.0
 */

define('BASEPATH', '.');    //Make this script work with nginx

/* if started from commandline, wrap parameters to $_POST */
if (!isset($_SERVER["HTTP_HOST"]))
    parse_str($argv[1], $_POST);

include "../application/config/database.php";
include "pretty_message.php";

$mysql = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);

if ($mysql->connect_error) {
    echo('Connection Error (' . $mysql->connect_errno . ') ' . $mysql->connect_error . '<br>');
    exit(2);
}

/* Setting DATA */
$data = array(
  // fields: data types, name, value, default
  array('ss','title', getenv('PHPBACK_TITLE')),
  array('ss','welcometext-title', 'Welcome to our feedback'),
  array('ss','welcometext-description', 'Here you can suggest ideas to improve our services or vote on ideas from other people'),
  array('ss','recaptchapublic', getenv('RECAPTCHA_PUBLIC_KEY')),
  array('ss','recaptchaprivate', getenv('RECAPTCHA_PRIVATE_KEY')),
  array('ss','language', getenv('PHPBACK_LANGUAGE') , 'english'),
  array('si','maxvotes', getenv('PHPBACK_MAX_VOTES'),  20),
  array('ss','mainmail', getenv('PHPBACK_EMAIL')),
  array('si','max_results', getenv('PHPBACK_MAX_RESULTS'), 10),
  array('ss','smtp-host', getenv('SMTP_HOST')),
  array('si','smtp-port' , getenv('SMTP_PORT'), 25),
  array('ss','smtp-user', getenv('SMTP_USER')),
  array('ss','smtp-pass', getenv('SMTP_PASSWORD')),
);

$stmt = $mysql->prepare("INSERT INTO `settings` (`name`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)");

foreach ($data as $entry) {
  list($type, $name, $value, $default) = array_pad($entry, 4, "");

  $name = $mysql->real_escape_string($name);
  $value = $mysql->real_escape_string(!empty($value)?$value:$default);

  $stmt->bind_param($type, $name, $value);

  /* execute prepared statement */
  $stmt->execute();
}

/* close statement and connection */
$stmt->close();

if(unlink('index2.php') && unlink('install2.php') && unlink('auto2.php') && unlink('pretty_message.php')) {
    header('Location: ../admin');
} else {
    $url = getBaseUrl();
    displayMessage("PLEASE DELETE install/ FOLDER MANUALLY. THEN GO TO <a href='" . $url . "/admin/'>yourwebsite.com/feedback/admin/</a> TO LOG IN");
}
