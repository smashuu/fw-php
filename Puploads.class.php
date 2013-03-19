<?php
/*!
 * @file
 * @author  Matthew Howell <smashuu@gmail.com>
 * @version 1.0
 *
 * @section LICENSE
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details at
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @section DESCRIPTION
 *
 * This class provides simple tools for handling file uploads
 */

class Puploads {
	private $files;
	
	/*!
	 * Creates a new instance of a Puploads object
	 */
	public function __construct() {
		foreach ($_FILES as $name=>$info) {
			$this->files[$name] = array();
			if (is_array($_FILES[$name]['tmp_name'])) {
				$this->files[$name] = array();
				foreach ($_FILES[$name]['tmp_name'] as $key=>$dummy) {
					$this->files[$name][$key] = array(
						'name'     => $_FILES[$name]['name'][$key],
						'type'     => $_FILES[$name]['type'][$key],
						'size'     => $_FILES[$name]['size'][$key],
						'tmp_name' => $_FILES[$name]['tmp_name'][$key],
						'error'    => $_FILES[$name]['error'][$key]
					);
				}
			}
			else
				$this->files[$name][] = $_FILES[$name];
		}
	}
	
	/*!
	 * Gets a count of uploads with the given field name
	 *
	 * @param string $name Upload field name to count
	 * @return The number of files, or 0 if nothing was uploaded under that name
	 */
	public function fileCount($name) {
		if ($this->files[$name])
			return count($this->files[$name]);
		else
			return 0;
	}
	
	/*!
	 * Gets an array of counts of uploaded files
	 * 
	 * @return An array of upload fields in the format: [ fieldName => fileCount ]
	 */
	public function fileList() {
		$output = array();
		foreach ($this->files as $name=>$info) {
			$output[$name] = $this->fileCount($this->files[$name]);
		}
		return $output;
	}
	
	/*!
	 * Gets an array of parameters of all uploads
	 * 
	 * @return An array in the format: [ fieldName => [ count => [name,type,size,tmp_name,error] ] ]
	 */
	public function getAll() {
		return $this->files;
	}
	
	/*!
	 * Gets an array of parameters of uploads with the given field name
	 *
	 * @param string $name Upload field name
	 * @return An array in the format: [ count => [name,type,size,tmp_name,error] ]
	 */
	public function getAllForName($name) {
		if (!$this->files[$name])
			throw new Exception('Upload name not found');
		else
			return $this->files[$name];
	}
	
	/*!
	 * Gets an array of parameters of a single upload with the given field name
	 * 
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return An array in the format: [name,type,size,tmp_name,error]
	 */
	public function getAllForUpload($name, $number=0) {
		if (!$this->files[$name])
			throw new Exception('Upload name not found');
		elseif (!$this->files[$name][$number])
			throw new Exception('Upload number not found');
		else
			return $this->files[$name][$number];
	}
	
	/*!
	 * Gets one parameter of a single upload with the given field name
	 * 
	 * @param string $field Upload parameter to return
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return The parameter's string or integer
	 */
	public function getFieldForUpload($field, $name, $number=0) {
		if (!$this->files[$name])
			throw new Exception('Upload name not found');
		elseif (!$this->files[$name][$number])
			throw new Exception('Upload number not found');
		elseif (!$this->files[$name][$number][$field])
			throw new Exception('Upload field not found');
		else
			return $this->files[$name][$number][$field];
	}
	
	/*!
	 * Gets the error, if any, for a single upload
	 *
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return The error code, or 0 if no error occurred
	 */
	public function error($name, $number=0) {
		try {
			return $this->getFieldForUpload('error', $name, $number);
		} catch (Exception $e) {
			return 0;
		}
	}
	
	/*!
	 * Moves an uploaded file to the specified destination
	 *
	 * @param string $destinationDir The destination directory
	 * @param string $destinationFile The destination filename -- if using the original filename be sure to sanitize it with cleanName()
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return The parameter's string or integer
	 */
	public function move($destinationDir, $destinationFile, $name, $number=0) {
		if (!is_writable($destinationDir))
			throw new Exception('Destination not writable');
		else {
			$tmpName = $this->getFieldForUpload('tmp_name', $name, $number);
			if (substr($destinationDir, -1, 1) != '/')
				$destinationDir .= '/';
			return move_uploaded_file($tmpName, $destinationDir.$destinationFile);
		}
	}
	
	/*!
	 * Calculates the hash of a single uploaded file
	 *
	 * @param string $algo The algorithm to use (such as 'MD5')
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return The parameter's string or integer
	 */
	public function hash($algo, $name, $number=0) {
		$tmpName = $this->getFieldForUpload('tmp_name', $name, $number);
		$file = file_get_contents($tmpName);
		return hash($algo, $tmpName);
	}
	
	/*!
	 * Returns a "clean" filename (characters that aren't alphanumeric, periods, or dashes are replaced with underscores) for a single uploaded file
	 *
	 * @param string $name Upload field name
	 * @param int $number (optional) The upload number, if more than one file was uploaded under the same field name
	 * @return The cleaned filename
	 */
	public function cleanName($name, $number) {
		$origName = $this->getFieldForUpload('name', $name, $number);
		return preg_replace('/[^[:alnum:]_\.-]/i', '_', $origName);
	}
}