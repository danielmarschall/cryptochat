<?php

class DirectoryHashCalculator {
	/*

	Directory Hash V3 by Daniel Marschall

	<directoryhash>      ::= SHA1(<directorycontext>)
	<directorycontext>   ::= "" | <entry>
	<entries>            ::= <entry> ["|" <entry>]
	                         IMPORTANT: (MD5) SORTED ASCENDING!
	<entry>              ::= <file_md5_hash> <filenames>
	<filenames>          ::= "*" <relative_directory> <filename> [<filenames>]
	                         IMPORTANT: (RelativeDir+Filename) SORTED ASCENDING!
	<file_md5_hash>      ::= MD5(FILECONTENT(<relative_directory> <filename>))
	<filename>           ::= given.
	                         IMPORTANT: All directories (this automatically
	                         excludes "", "." and "..") and non-existing resp.
	                         non-readable files are not included.
	                         addFile() will return false.
	<relative_directory> ::= given.
	                         Note: Usually, the directory is in relative diction.
	                         IMPORTANT: "./" is always stripped from beginning!
	                         IMPORTANT: "\" is made to "/"!

	Example:
		"" --> Empty directory
		<hash1>*<file1_with_hash1>*<file2_with_hash1>|<hash2>*<file1_with_hash2>
	
	*/

	private $hashes;

	function __construct() {
		$this->clear();
	}

	private static function makeFilenameConsistently(&$filename) {
		// Rule 1: Cut off "./" from beginning
		if (substr($filename, 0, 2) == './') {
			$filename = substr($filename, 2, strlen($filename)-2);
		}
		
		// Rule 2: Use "/" instead of "\"
		$filename = str_replace('\\', '/', $filename);
	}

	/*
	@return
		MD5-Hash of the file or FALSE is calculation/adding
		was not successful.
	*/
	public function addFile($file) {
		if (!file_exists($file)) return false;
		// if (!is_readable($file)) return false;
		// if (basename($file) == '') return false;
		// if (basename($file) == '.') return false;
		// if (basename($file) == '..') return false;
		self::makeFilenameConsistently($file);
		$file_md5 = md5_file($file);
		if ($file_md5 === false) return false; // Error...
		$this->hashes[$file_md5][] = $file;
		return $file_md5;
	}

	public function clear() {
		$this->hashes = array();
	}

	private function getDirectoryContext() {
		if (count($this->hashes) == 0) return '';
		$directory_context = '';
		// Sort md5 hashes ascending (so that the result is equal at every machine)
		ksort($this->hashes);
		foreach ($this->hashes as $hash => $filenames) {
			// Sort filenames ascending (so that the result is equal at every machine)
			sort($filenames);
			$directory_context .= $hash;	
			foreach ($filenames as $filename) {
				$directory_context .= '*'.$filename;
			}
			$directory_context .= '|';
		}
		$directory_context = substr($directory_context, 0, strlen($directory_context)-1);
		return $directory_context;	
	}

	public function calculateDirectoryHash() {
		$directory_context = $this->getDirectoryContext();
		return sha1($directory_context);
	}
	
	function getVersionDescription() {
		return 'Marschall V3';
	}
}

?>