<?php

/**
 * Import an OpenOffice thesaurus into a simple synonyms SQL format.
 */

namespace FFW\SEACH_TOOLS\SYNONYMS;

use \PDO;

class ImportThesaurus {

  /* Internal variables. */

  /**
   * Path to the Thesaurus index file.
   */
  private $idx_file;

  /**
   * Path to the Thesaurus dat file.
   */
  private $dat_file;

  /**
   * Language code for the language we are importing the synonyms for.
   */
  private $lang_code;

  /**
   * File pointer to the Thesaurus index file.
   */
  private $fp_idx_file;

  /**
   * File pointer to the Thesaurus dat file.
   */
  private $fp_dat_file;

  /**
   * Path to file with black listed words.
   */
  private $black_list_file;

  /**
   * Array with black listed words.
   */
  private $black_list;

  /**
   * ImportThesaurus constructor.
   *
   * @param string $idx_file
   *   Path and file name to the Thesaurus index file.
   *
   * @param string $dat_file
   *   Path and file name to the Thesaurus dat file.
   *
   * @param string $lang_code
   *   Language code for the language we are importing the synonyms for.
   *
   * @param string $black_list_file
   *   File with black listed words.
   *
   */
  function __construct($idx_file, $dat_file, $lang_code = 'da_DK', $black_list_file = 'black_list.txt') {
    $this->idx_file = $idx_file;
    $this->dat_file =  $dat_file;
    $this->lang_code = $lang_code;
    $this->black_list_file = $black_list_file;

    $this->open_idx_file();
    $this->open_dat_file();

    if ($this->fp_idx_file == NULL || $this->fp_dat_file == NULL) {
      return "ERROR: Can't open one or more of the Thesaurus files.";
    }

    // @todo: Replace with external configuration.
    $dsn = 'mysql:dbname=synonyms;host=127.0.0.1';
    $user = 'root';
    $password = 'root';

    try {
      $this->pdo = new PDO($dsn, $user, $password);
    }
    catch (PDOException $e) {
      echo 'Connection failed: ' . $e->getMessage();
    }
  }

  /**
   * Import the basic words from the Thesaurus index file.
   */
  public function import_words() {
    // Get black listed words.
    $black_list = $this->get_black_list();

    // Truncate table before starting
    $this->truncate_synonyms();

    // Loop through all words in index file.
    while(!feof($this->fp_idx_file)){
      $line = explode('|', fgets($this->fp_idx_file));
      if (!empty($line[0]) && !empty($line[1]) && !isset($black_list[$line[0]])) {
        $this->import_word($line[0], (int) $line[1]);
      }
    }
  }

  /**
   * Import the synonyms from the Thesaurus data file.
   */
  public function import_synonyms() {
    $stmt = $this->pdo->prepare('SELECT id, byte_offset FROM synonyms WHERE lang_code = :lang_code');
    $stmt->bindValue(':lang_code', $this->lang_code);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
      $this->import_synonym($row->id, $row->byte_offset);
     }
  }

  /**
   * Import a single word into the synonyms db table.
   *
   * @param string $word
   *   The word from the index file.
   *
   * @param integer $byte_offset
   *   The byte offset used to lookup the word in the dat file.
   */
  private function import_word($word, $byte_offset) {
    $stmt = $this->pdo->prepare('INSERT INTO synonyms (word, byte_offset, lang_code) VALUES(:word, :byte_offset, :lang_code)');
    $stmt->bindValue(':word', utf8_decode($word));
    $stmt->bindValue(':byte_offset', $byte_offset);
    $stmt->bindValue(':lang_code', $this->lang_code);
    $stmt->execute();
  }

  /**
   * Import synonyms for a single word represented by a byte offset.
   *
   * @param integer $word_id
   *   The unique ID used in the synonyms db table for the word we are finding synonyms for.
   *
   * @param integer $byte_offset
   *   The byte offset used to lookup the word in the dat file.
   */
  private function import_synonym($word_id, $byte_offset) {
    $synonyms = array();

    // Find all synonyms for the word / byte offset.
    $rows = $this->find_synonyms($byte_offset);

    // Prepare the synonyms and remove the part wrapped in ().
    foreach ($rows as $row) {
      $words = explode('|', $row);
      for ($i = 1; $i < count($words); $i++) {
        $word = trim(preg_replace('/\((.*?)\)/', '', $words[$i]));
        $synonyms[$word] = $word;
      }
    }

    // Update the word in the db table with the found synonyms
    if (count($synonyms) > 0) {
      $stmt = $this->pdo->prepare('UPDATE synonyms SET synonyms = :synonyms WHERE id = :id');
      $stmt->bindValue(':synonyms', utf8_decode(implode(', ', $synonyms)));
      $stmt->bindValue(':id', $word_id);
      $stmt->execute();
    }
  }

  /**
   * Find all synonyms for a byte offset.
   *
   * @param integer $byte_offset
   *   The byte offset used to lookup synonyms in the dat file.
   *
   * @return array
   *   The rows found in the dat file.
   */
  private function find_synonyms($byte_offset) {
    fseek($this->fp_dat_file, $byte_offset);

    $rows = array();
    $entry = explode('|', fgets($this->fp_dat_file));

    for ($i = 0; $i < $entry[1]; $i++) {
      $rows[] = fgets($this->fp_dat_file);
    }

    return $rows;
  }

  /**
   * Truncate synonyms db table for the active lang_code
   */
  private function truncate_synonyms() {
    $stmt = $this->pdo->prepare('DELETE FROM synonyms WHERE lang_code = :lang_code');
    $stmt->bindValue(':lang_code', $this->lang_code);
    $stmt->execute();
  }

  /**
   * Open the Thesaurus index file and save the file pointer.
   */
  private function open_idx_file() {
    $this->fp_idx_file = fopen($this->idx_file, "r");
  }

  /**
   * Open the Thesaurus dat file and save the file pointer.
   */
  private function open_dat_file() {
    $this->fp_dat_file = fopen($this->dat_file, "r");
  }

  /**
   * Read the black list word file into an array.
   *
   * @return array
   *   With black listed words.
   */
  private function get_black_list() {
    if (empty($this->black_list)) {
      $this->black_list = array_flip(array_map('trim', file($this->black_list_file)));
    }
    return $this->black_list;
  }

}

$time_start = microtime(TRUE);

use FFW\SEACH_TOOLS\SYNONYMS\ImportThesaurus as Importer;
$importer = new Importer('th_da_DK.idx', 'th_da_DK.dat', 'da_DK');
#$importer->import_words();
$importer->import_synonyms();

$time_end = microtime(TRUE);
$execution_time = ($time_end - $time_start);
echo '<b>Total Execution Time:</b> '.$execution_time.' seconds';
