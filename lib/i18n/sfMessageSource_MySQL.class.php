<?php
/** @noinspection PhpMissingParentConstructorInspection */

/**
 * sfMessageSource_MySQL class file.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the BSD License.
 *
 * Copyright(c) 2004 by Qiang Xue. All rights reserved.
 *
 * To contact the author write to {@link mailto:qiang.xue@gmail.com Qiang Xue}
 * The latest version of PRADO can be obtained from:
 * {@link http://prado.sourceforge.net/}
 *
 * @author     Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @version    $Id$
 * @package    symfony
 * @subpackage i18n
 */

/**
 * sfMessageSource_MySQL class.
 *
 * Retrieve the message translation from a MySQL database.
 *
 * See the MessageSource::factory() method to instantiate this class.
 *
 * MySQL schema:
 *
 * CREATE TABLE `catalogue` (
 *   `cat_id` int(11) NOT NULL auto_increment,
 *   `name` varchar(100) NOT NULL default '',
 *   `source_lang` varchar(100) NOT NULL default '',
 *   `target_lang` varchar(100) NOT NULL default '',
 *   `date_created` int(11) NOT NULL default '0',
 *   `date_modified` int(11) NOT NULL default '0',
 *   `author` varchar(255) NOT NULL default '',
 *   PRIMARY KEY  (`cat_id`)
 * ) TYPE=InnoDB;
 *
 * CREATE TABLE `trans_unit` (
 *   `msg_id` int(11) NOT NULL auto_increment,
 *   `cat_id` int(11) NOT NULL default '1',
 *   `id` varchar(255) NOT NULL default '',
 *   `source` text NOT NULL,
 *   `target` text NOT NULL,
 *   `comments` text NOT NULL,
 *   `date_added` int(11) NOT NULL default '0',
 *   `date_modified` int(11) NOT NULL default '0',
 *   `author` varchar(255) NOT NULL default '',
 *   `translated` tinyint(1) NOT NULL default '0',
 *   PRIMARY KEY  (`msg_id`)
 * ) TYPE=InnoDB;
 *
 * Propel schema (in .xml format):
 *
 *  <database ...>
 *    ...
 *    <table name="catalogue">
 *     <column name="cat_id" type="integer" required="true" primaryKey="true" autoincrement="true" />
 *     <column name="name" type="varchar" size="100" />
 *     <column name="source_lang" type="varchar" size="100" />
 *     <column name="target_lang" type="varchar" size="100" />
 *     <column name="date_created" type="timestamp" />
 *     <column name="date_modified" type="timestamp" />
 *     <column name="author" type="varchar" size="255" />
 *    </table>
 *
 *    <table name="trans_unit">
 *     <column name="msg_id" type="integer" required="true" primaryKey="true" autoincrement="true" />
 *     <column name="cat_id" type="integer" />
 *       <foreign-key foreignTable="catalogue" onDelete="cascade">
 *         <reference local="cat_id" foreign="cat_id"/>
 *       </foreign-key>
 *     <column name="id" type="varchar" size="255" />
 *     <column name="source" type="longvarchar" />
 *     <column name="target" type="longvarchar" />
 *     <column name="comments" type="longvarchar" />
 *     <column name="date_created" type="timestamp" />
 *     <column name="date_modified" type="timestamp" />
 *     <column name="author" type="varchar" size="255" />
 *     <column name="translated" type="integer" />
 *    </table>
 *    ...
 *  </database>
 *
 * @author Xiang Wei Zhuo <weizhuo[at]gmail[dot]com>
 * @version v1.0, last update on Fri Dec 24 16:58:58 EST 2004
 * @package    symfony
 * @subpackage i18n
 */
class sfMessageSource_MySQL extends sfMessageSource_Database
{
  /**
   * The datasource string, full DSN to the database.
   * @var string
   */
  protected $source;

  /**
   * The DSN array property, parsed by PEAR's DB DSN parser.
   * @var array
   */
  protected $dsn;

  /**
   * A resource link to the database
   * @var \mysqli
   */
  protected $db;

  /**
   * Constructor.
   * Creates a new message source using MySQL.
   *
   * @param string $source MySQL datasource, in PEAR's DB DSN format.
   * @see MessageSource::factory();
   * @throws \sfException
   */
  protected function __construct($source)
  {
    $this->source = (string)$source;
    $this->dsn = $this->parseDSN($this->source);
    $this->db = $this->connect();
  }

  /**
   * Destructor, closes the database connection.
   */
  public function __destruct()
  {
    if ($this->db) {
      $this->db->close();
    }
  }

  /**
   * Connects to the MySQL datasource
   *
   * @return \mysqli MySQL connection.
   * @throws sfException, connection and database errors.
   */
  protected function connect()
  {
    $dsninfo = $this->dsn;

    if (isset($dsninfo['protocol']) && $dsninfo['protocol'] == 'unix') {
      $dbhost = ':' . $dsninfo['socket'];
    } else {
      $dbhost = $dsninfo['hostspec'] ?: 'localhost';
      if (!empty($dsninfo['port'])) {
        $dbhost .= ':' . $dsninfo['port'];
      }
    }
    $user = $dsninfo['username'];
    $pw = $dsninfo['password'];

    if (!class_exists('mysqli')) {
      throw new RuntimeException('The class mysqli does not exist. Please confirm MySQLi is enabled in php.ini');
    }

    if ($dbhost && $user && $pw) {
      $conn = new \mysqli($dbhost, $user, $pw, $dsninfo['database']);
    } elseif ($dbhost && $user) {
      $conn = new \mysqli($dbhost, $user, null, $dsninfo['database']);
    } elseif ($dbhost) {
      $conn = new \mysqli($dbhost, null, null, $dsninfo['database']);
    } else {
      throw new sfException(sprintf('Error in connecting to %s.', $dsninfo));
    }

    return $conn;
  }

  /**
   * Gets the database connection.
   *
   * @return \mysqli database connection.
   */
  public function connection()
  {
    return $this->db;
  }

  /**
   * Gets an array of messages for a particular catalogue and cultural variant.
   *
   * @param string $variant the catalogue name + variant
   * @return array translation messages.
   */
  public function &loadData($variant)
  {
    $variant = $this->db->real_escape_string($variant);

    $statement =
      "SELECT t.id, t.source, t.target, t.comments
        FROM trans_unit t, catalogue c
        WHERE c.cat_id =  t.cat_id
          AND c.name = '{$variant}'
        ORDER BY id ASC";

    $rs = $this->db->query($statement);

    $result = array();

    while ($row = $rs->fetch_array(MYSQLI_NUM)) {
      $source = $row[1];
      $result[$source][] = $row[2]; //target
      $result[$source][] = $row[0]; //id
      $result[$source][] = $row[3]; //comments
    }

    return $result;
  }

  /**
   * Gets the last modified unix-time for this particular catalogue+variant.
   * We need to query the database to get the date_modified.
   *
   * @param string $source catalogue+variant
   * @return int last modified in unix-time format.
   */
  protected function getLastModified($source)
  {
    $source = $this->db->real_escape_string($source);

    $rs = $this->db->query("SELECT date_modified FROM catalogue WHERE name = '{$source}'");

    return $rs ? (int)$rs->fetch_row()[0] : 0;
  }

  /**
   * Checks if a particular catalogue+variant exists in the database.
   *
   * @param string $variant catalogue+variant
   * @return boolean true if the catalogue+variant is in the database, false otherwise.
   */
  public function isValidSource($variant)
  {
    $variant = $this->db->real_escape_string($variant);

    $rs = $this->db->query("SELECT COUNT(*) FROM catalogue WHERE name = '{$variant}'");

    $row = $rs->fetch_array(MYSQLI_NUM);

    return $row && $row[0] == '1';
  }

  /**
   * Retrieves catalogue details, array($cat_id, $variant, $count).
   *
   * @param string $catalogue catalogue
   * @return array|bool catalogue details, array($cat_id, $variant, $count).
   */
  protected function getCatalogueDetails($catalogue = 'messages')
  {
    if (empty($catalogue)) {
      $catalogue = 'messages';
    }

    $variant = $catalogue . '.' . $this->culture;

    $name = $this->db->real_escape_string($this->getSource($variant));

    $rs = $this->db->query("SELECT cat_id FROM catalogue WHERE name = '{$name}'");

    if ($rs->num_rows != 1) {
      return false;
    }

    $cat_id = (int)$rs->fetch_row()[0];

    // first get the catalogue ID
    $rs = $this->db->query("SELECT COUNT(*) FROM trans_unit WHERE cat_id = {$cat_id}");

    $count = (int)$rs->fetch_row()[0];

    return array($cat_id, $variant, $count);
  }

  /**
   * Updates the catalogue last modified time.
   *
   * @param int $cat_id
   * @param $variant
   * @return boolean true if updated, false otherwise.
   */
  protected function updateCatalogueTime($cat_id, $variant)
  {
    $time = time();

    $result = $this->db->query("UPDATE catalogue SET date_modified = {$time} WHERE cat_id = {$cat_id}");

    if ($this->cache) {
      $this->cache->remove($variant . ':' . $this->culture);
    }

    return $result;
  }

  /**
   * Saves the list of untranslated blocks to the translation source.
   * If the translation was not found, you should add those
   * strings to the translation source via the <b>append()</b> method.
   *
   * @param string $catalogue the catalogue to add to
   * @return boolean true if saved successfuly, false otherwise.
   */
  public function save($catalogue = 'messages')
  {
    $messages = $this->untranslated;

    if (count($messages) <= 0) {
      return false;
    }

    $details = $this->getCatalogueDetails($catalogue);

    if ($details) {
      list($cat_id, $variant, $count) = $details;
    } else {
      return false;
    }

    if ($cat_id <= 0) {
      return false;
    }
    $inserted = 0;

    $time = time();

    foreach ($messages as $message) {
      $count++;
      $message = $this->db->real_escape_string($message);
      $statement = "INSERT INTO trans_unit
        (cat_id,id,source,date_added) VALUES
        ({$cat_id}, {$count},'{$message}',$time)";
      $this->db->query($statement) ? $inserted++ : false;
    }
    if ($inserted > 0) {
      $this->updateCatalogueTime($cat_id, $variant);
    }

    return $inserted > 0;
  }

  /**
   * Deletes a particular message from the specified catalogue.
   *
   * @param string $message the source message to delete.
   * @param string $catalogue the catalogue to delete from.
   * @return boolean true if deleted, false otherwise.
   */
  public function delete($message, $catalogue = 'messages')
  {
    $details = $this->getCatalogueDetails($catalogue);
    if ($details) {
      list($cat_id, $variant, $count) = $details;
    } else {
      return false;
    }

    $message = $this->db->real_escape_string($message);

    $statement = "DELETE FROM trans_unit WHERE cat_id = {$cat_id} AND source = '{$message}'";
    $deleted = false;

    $rs = $this->db->query($statement);

    if ($rs && $this->db->affected_rows == 1) {
      $deleted = $this->updateCatalogueTime($cat_id, $variant);
    }

    return $deleted;
  }

  /**
   * Updates the translation.
   *
   * @param string $text the source string.
   * @param string $target the new translation string.
   * @param string $comments comments
   * @param string $catalogue the catalogue of the translation.
   * @return boolean true if translation was updated, false otherwise.
   */
  public function update($text, $target, $comments, $catalogue = 'messages')
  {
    $details = $this->getCatalogueDetails($catalogue);
    if ($details) {
      list($cat_id, $variant, $count) = $details;
    } else {
      return false;
    }

    $comments = $this->db->real_escape_string($comments);
    $target = $this->db->real_escape_string($target);
    $text = $this->db->real_escape_string($text);

    $time = time();

    $statement = "UPDATE trans_unit
        SET target = '{$target}', comments = '{$comments}', date_modified = '{$time}' WHERE cat_id = {$cat_id} AND source = '{$text}'";

    $updated = false;

    $rs = $this->db->query($statement, $this->db);
    if ($rs && $this->db->affected_rows == 1) {
      $updated = $this->updateCatalogueTime($cat_id, $variant);
    }

    return $updated;
  }

  /**
   * Returns a list of catalogue as key and all it variants as value.
   *
   * @return array list of catalogues
   */
  public function catalogues()
  {
    $statement = 'SELECT name FROM catalogue ORDER BY name';
    $rs = $this->db->query($statement);
    $result = array();
    while ($row = $rs->fetch_array(MYSQLI_NUM)) {
      $details = explode('.', $row[0]);
      if (!isset($details[1])) {
        $details[1] = null;
      }

      $result[] = $details;
    }

    return $result;
  }
}
