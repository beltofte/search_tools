<?php

/**
 * Export synonyms from SQL to Apache Solr synonyms.txt file.
 */

namespace FFW\SEACH_TOOLS\SYNONYMS;

use \PDO;

class GenerateSynonymsFile {

  /* Internal variables. */

  /**
   * Path to the synonyms.txt file
   */
  private $synonyms_file;

  /**
   * Language code we are generating the synonyms.txt file for.
   */
  private $lang_code;

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
  function __construct($synonyms_file = 'synonyms.txt', $lang_code = 'da_DK') {
    $this->synonyms_file = $synonyms_file;
    $this->lang_code = $lang_code;

    // @todo: Replace with external configuration.
    $dsn = 'mysql:dbname=synonyms;host=127.0.0.1';
    $user = 'root';
    $password = 'root';

    try {
      $this->pdo = new PDO($dsn, $user, $password);
      $this->pdo->exec("SET NAMES utf8");
    }
    catch (PDOException $e) {
      echo 'Connection failed: ' . $e->getMessage();
    }
  }

  /**
   * Generate the synonyms.txt file from synonyms in SQL.
   */
  public function generate_synonyms_file() {
    $fp = fopen($this->synonyms_file, "w+");

    if ($fp== NULL) {
      return "ERROR: Can't read the synonyms.txt file!";
    }

    fwrite($fp, $this->generate_header());

    $stmt = $this->pdo->prepare('SELECT word, synonyms FROM synonyms WHERE lang_code = :lang_code');
    $stmt->bindValue(':lang_code', $this->lang_code);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
      $line = $this->generate_line($row->word, $row->synonyms);
      fwrite($fp, $line);
    }

    fclose($fp);

    return "SUCCESS: synonyms.txt file generated successfully.";
  }

  /**
   * Generate the header lines in the synonyms.txt file.
   *
   * @return string
   *   Return the header lines for the synonyms.txt file.
   */
  private function generate_header() {
    $lines = array();
    $lines[] = "#";

    switch ($this->lang_code) {
      case 'da_DK':
        $lines[] = "# --- Synonyms data ---";
        $lines[] = "# The synonyms is based on the Danish thesaurus for OpenOffice / MyThes.";
        $lines[] = "# © 2015 Foreningen for frit tilgængelige sprogværktøjer - http://www.stavekontrolden.dk";
        $lines[] = "#";
        break;
    }

    $lines[] = "# --- Conversion to synonyms.txt ---";
    $lines[] = "# The conversion of the thesaurus from MyThes-format to Apache Solr synonyms.txt was done";
    $lines[] = "# by FFW - http://ffwagency.com / Jens Beltofte.";
    $lines[] = "# © 2016 FFW - http://ffwagency.com.";
    $lines[] = "#";
    $lines[] = "# The files are published under the following open source licenses:";
    $lines[] = "#";
    $lines[] = "# GNU GPL version 2.0";
    $lines[] = "# GNU LGPL version 2.1";
    $lines[] = "# Mozilla MPL version 1.1";
    $lines[] = "#";
    $lines[] = "#---------------------------------------------------";
    $lines[] = "#";
    $lines[] = "";
    $lines[] = "";

    return implode("\n", $lines);
  }

  /**
   * Generate a single synonyms line for the synonyms.txt file.
   *
   * @param string $word
   *   The main word.
   *
   * @param string $synonyms
   *   The comma separated string with synonyms.
   *
   * @return string
   *   Return the single line with synonyms and the corresponding word.
   */
  private function generate_line($word, $synonyms) {
    return "{$synonyms} => {$word}\n";
  }

}

$time_start = microtime(TRUE);

use FFW\SEACH_TOOLS\SYNONYMS\GenerateSynonymsFile as Generator;
$generator = new Generator('synonyms.txt', 'da_DK');
$generator->generate_synonyms_file();

$time_end = microtime(TRUE);
$execution_time = ($time_end - $time_start);
echo '<b>Total Execution Time:</b> '.$execution_time.' seconds';
