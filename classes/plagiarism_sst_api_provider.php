<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   plagiarism_sst
 * @copyright 2023, SmallSEOTools <support@smallseotools.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class provider HTTP-API methods.
 */
class plagiarism_sst_api_provider {

    /**
     * Auth key.
     *
     * @var string
     */
    private $key;

    /**
     * Auth token.
     *
     * @var string
     */
    private $token;

    /**
     * Url of api.
     *
     * @var string
     */
    private $endpoint;

    /**
     * Last api error.
     *
     * @var string|null
     */
    private $lasterror;

    /**
     * Fetch last api error.
     *
     * @return mixed
     */
    public function get_last_error() {
        return $this->lasterror;
    }

    /**
     * Setup last api error.
     *
     * @param mixed $lasterror
     */
    public function set_last_error($lasterror) {
        $this->lasterror = $lasterror;
    }

    /**
     * Constructor for api provider.
     *
     * @param $token
     * @param string $endpoint
     */
    public function __construct($key, $token, $endpoint = 'http://v2.smallseotools.com/api/mdl') {
        $this->key = $key;
        $this->token = $token;
        $this->endpoint = $endpoint;
    }


    /**
     * Send file for originality check.
     *
     * @param $text_content
     * @param $file
     *
     * @return |null
     */
    public function send_text($text_content, $file) {
        $mime_type = mime_content_type( $file );
        $post_data = array(
            'key' => $this->key,
            'token' => $this->token,
            'file' => new \CURLFile($file, $mime_type)
        );

        $options = array(
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 0,
            'CURLOPT_ENCODING' => '',
        );
        $curl = new curl();
        $response = $curl->post($this->endpoint. '/checkplag', $post_data, $options);
        if ($response = json_decode($response)) {
            if (isset($response->message)) {
                return $response;
            }
            if (isset($response->hash) && $response->hash) {
                do {
                    $response = $curl->get($this->endpoint. '/query-footprint/'. $response->hash .'/'. $response->key);
                    $response = json_decode($response);
                }
                while($response->recall);
                return $response;
            }
        }
        return null;
    }

}
