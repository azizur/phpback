<?php
/**
 * Auto install
 * @copyright  Copyright (c) 2016 ProDevStudio
 * @author       Azizur Rahman <hi@azizur.com>
 * @license      http://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link            https://github.com/azizur/phpback
 * @since         1.0
 */
define('APPLICATION_LOADED', true);
define('BASEPATH', '.');    //Make this script work with nginx

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'pretty_message.php';
include '../application/libraries/Hashing.php';

$hashing = new Hashing();

/**
 * Redirect to the initial form and pass to the page an array containing :
 * 1. Previous entered values
 * 2. Error messages if any
 * @param string $url URL of the action page
 * @param array $data array containing the previous posted values in the form
 */
function redirectPost($url, array $data) {
    echo '<html><head>
        <script type="text/javascript">
            function close() {
                document.forms["redirectpost"].submit();
            }
        </script>
        <title>Please Wait...</title>
    </head>
    <body onload="close();">
    Please Wait...<br />
    <form name="redirectpost" method="post" action="' . $url . '">';
    if (!is_null($data)) {
        foreach ($data as $k => $v) {
            echo '<input type="hidden" name="' . $k . '" value="' . $v . '"> ';
        }
    }
    echo "</form></body></html>";
    exit(1);
}

/**
 * An error was encountered, so send back to the initial form
 * @param string $errorMessage Error message sent back by the database driver
 */
function exitOnError($errorMessage) {
    $data['error'] = $errorMessage;
    redirectPost('index.php', $data);
}

/**
 * Create the CodeIgniter database configuration file
 */
function createDbConfigFile() {
    @chmod('../application/config', 0777);
    if (($file = fopen('../application/config/database.php', 'w+')) == FALSE) {
        exitOnError('ERROR #1: Config file could not be created');
    }

    $content[]= '<?php ';
    $content[]= '//Configuration generated by install script';
    $content[]= '$active_group = \'default\';';
    $content[]= '$active_record = TRUE;';
    $content[]= '$db[\'default\'][\'hostname\'] = getenv(\'MYSQL_HOST\');';
    $content[]= '$db[\'default\'][\'username\'] = getenv(\'MYSQL_USER\');';
    $content[]= '$db[\'default\'][\'password\'] = getenv(\'MYSQL_PASSWORD\');';
    $content[]= '$db[\'default\'][\'database\'] = getenv(\'MYSQL_DATABASE\');';
    $content[]= '$db[\'default\'][\'dbdriver\'] = \'mysqli\';';
    $content[]= '$db[\'default\'][\'dbprefix\'] = \'\';';
    $content[]= '$db[\'default\'][\'pconnect\'] = TRUE;';
    $content[]= '$db[\'default\'][\'db_debug\'] = TRUE;';
    $content[]= '$db[\'default\'][\'cache_on\'] = FALSE;';
    $content[]= '$db[\'default\'][\'cachedir\'] = \'\';';
    $content[]= '$db[\'default\'][\'char_set\'] = \'utf8\';';
    $content[]= '$db[\'default\'][\'dbcollat\'] = \'utf8_general_ci\';';
    $content[]= '$db[\'default\'][\'swap_pre\'] = \'\';';
    $content[]= '$db[\'default\'][\'autoinit\'] = TRUE;';
    $content[]= '$db[\'default\'][\'stricton\'] = FALSE;';

    $contents = implode(PHP_EOL, $content);

    if (fwrite($file, $contents) == FALSE) {
        fclose($file);
        exitOnError('ERROR #1: Config file could not be created');
    }
    fclose($file);
}

$server = new mysqli(getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'));

if ($server->connect_error) {
    $str = mb_convert_encoding($server->connect_error, "UTF-8", "auto");
    exitOnError('ERROR #2: Server connection error (' . $server->connect_errno . ') ' . $str);
}

if (getenv('MYSQL_HOST') !== false && getenv('MYSQL_USER') !== false && getenv('MYSQL_PASSWORD') !== false && getenv('MYSQL_DATABASE') !== false) {

    if (!file_exists('../application/config/database.php')) {
      createDbConfigFile();
    }

    include '../application/config/database.php';
    if (!(getenv('MYSQL_HOST') == $db['default']['hostname'] && getenv('MYSQL_USER') == $db['default']['username'] && getenv('MYSQL_PASSWORD') == $db['default']['password'] && getenv('MYSQL_DATABASE') == $db['default']['database'])) {
      exitOnError('Config file does not match with the given information');
    }

    if ($server->select_db(getenv('MYSQL_DATABASE')) === FALSE) {
      exitOnError("ERROR #3: Couldn't connect to database");
    }

    $query = file_get_contents('database_tables.sql');
    if ($server->multi_query($query) === FALSE) {
      exitOnError("ERROR #4: Couldn't create the tables");
    }

} else {
    if (!file_exists('../application/config/database.php'))
        createDbConfigFile();

    if ($server->select_db(getenv('MYSQL_DATABASE')) === TRUE) {
      exitOnError("ERROR #5: You already have a phpback database, please create another manually");
    }

    if (!$server->query("CREATE DATABASE IF NOT EXISTS ". getenv('MYSQL_DATABASE') ." CHARACTER SET utf8 COLLATE utf8_general_ci")) {
      exitOnError("ERROR #6: Could not create database");
    }

    if ($server->select_db(getenv('MYSQL_DATABASE')) === FALSE) {
      exitOnError("ERROR #5: Generated database connection error");
    }

    $sql = file_get_contents('database_tables.sql');
    if ($server->multi_query($sql) === FALSE) {
      exitOnError("ERROR #4: Couldn't create the tables");
    }
}

do {
    if ($r = $server->store_result())
        $r->free();
} while ($server->more_results() && $server->next_result());

$result = $server->query("SELECT id FROM settings WHERE name='title'");

if ($result->num_rows == 1) {
    if (!@chmod('../install', 0777)) {
        echo "PLEASE DELETE install/ FOLDER MANUALLY. THEN GO TO yourwebsite.com/feedback/admin/ TO LOG IN.";
        exit;
    }

    //In case of success (by using previously set parameters), delete the content of installation folder
    unlink('index.php');
    unlink('install1.php');
    unlink('database_tables.sql');
    unlink('index2.php');
    unlink('install2.php');
    unlink('auto.php');
    header('Location: ../admin');
    exit;
} else {
    $server->query("INSERT INTO users(name,email,pass,votes,isadmin,banned) VALUES('" . getenv('ADMIN_USER') . "','" . getenv('ADMIN_EMAIL') . "','" . $hashing->hash( getenv('ADMIN_PASSWORD') ) . "', 20, 3,0)");

    if (!@chmod('../install', 0777)) {
        $url = getBaseUrl();
        displayMessage("PLEASE DELETE install/index.php, install/install1.php AND install/database_tables.sql FILES MANUALLY.<br />
            THEN GO TO <a href='" . $url . "/install/index2.php'>yourwebsite.com/feedback/install/index2.php</a> TO CONTINUE THE INSTALLATION.");
        exit;
    }

    //In case of success, delete the installation files of the first step
    unlink('index.php');
    unlink('install1.php');
    unlink('database_tables.sql');
    unlink('auto.php');
    header('Location: auto2.php');
}