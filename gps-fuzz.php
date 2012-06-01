<?php
/**
 * @package GPS_Fuzz
 * @version 1.0
 */
/*
Plugin Name: GPS Fuzz
Plugin URI: http://github.com/ziz/wp-gps-fuzz
Description: Fuzz GPS exif data in uploaded JPEGs
Author: Justin de Vesine
Version: 1.0
Author URI: http://wizardmode.com/

Copyright (c) 2012 Justin de Vesine

Permission is hereby granted, free of charge, to any person obtaining a copy of 
this software and associated documentation files (the "Software"), to deal in 
the Software without restriction, including without limitation the rights to 
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies 
of the Software, and to permit persons to whom the Software is furnished to do 
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all 
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE 
SOFTWARE.

*/
/* init */
include('PelJpeg.php');
if (class_exists('PelJpeg')) {
	add_action('plugins_loaded', 'gf_init');
}
else {
	add_action('admin_notices', 'gf_no_pel');
}

function gf_no_pel() {
	$message = sprintf(__('GPS_Fuzz requires the installation of the <a href="%s" target="_blank">PHP Exif Library</a>.', 'gpsfuzz'), esc_url('http://lsolesen.github.com/pel/'));
	echo '<div class="error"><p>'.$message.'</p></div>';
}


function gf_init() {
	add_action('wp_handle_upload', 'gf_fuzz_gps'); // apply our modifications
}

function gf_fuzz_gps($array) {
        // $array contains file, url, type

        if ($array['type'] == 'image/jpeg' || $array['type'] == 'image/jpg') {
			gf_fuzz_gps_file($array['file']);
        }

        return $array;
}


function gf_fuzz_gps_file($file) {
	$old = error_reporting(0);
	try {
		$image = new PelJpeg($file);
		error_reporting($old);
		if (!$image) { return; }
		$exif = $image->getExif();
		if (!$exif) { return; }
		$tiff = $exif->getTiff();
		if (!$tiff) { return; }
		$ifd0 = $tiff->getIfd();
		if (!$ifd0) { return; }
		$gps = $ifd0->getSubIfd(PelIfd::GPS);
		if (!$gps) { return; }
		foreach (array(PelTag::GPS_LATITUDE, PelTag::GPS_LONGITUDE, PelTag::GPS_DEST_LATITUDE, PelTag::GPS_DEST_LONGITUDE) as $tag) {
			$pos = $gps->getEntry($tag);
			if (is_null($pos)) continue;
			$val = $pos->getValue();
			$data = $val[1][0] / $val[1][1];
			$data = round($data);
			$val[1][0] = $data * $val[1][1];

			$val[2][0] = 0;
			$pos->setValueArray($val);
		}

		@file_put_contents($file, $image->getBytes());
	} catch (Exception $e) {
		error_reporting($old);
		return;
	}

}
