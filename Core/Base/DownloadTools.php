<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2019 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Base;

use Exception;
use FacturaScripts\Core\Base\MiniLog;

/**
 * Description of DownloadTools
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class DownloadTools
{

    const USERAGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36';
    const TIMEOUT = 30;

    /**
     * Downloads and returns url content with curl or file_get_contents.
     * 
     * @param string $url
     * @param int    $timeout
     * 
     * @return string
     */
    public function getContents(string $url, int $timeout = self::TIMEOUT)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USERAGENT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            switch ($httpCode) {
                case 200:
                    curl_close($ch);
                    return $data;

                case 301:
                case 302:
                case 303:
                    $redirs = 0;
                    return $this->curlRedirectExec($ch, $redirs);

                case 404:
                    curl_close($ch);
                    return 'ERROR';
            }

            /// save in log
            $error = curl_error($ch) === '' ? 'ERROR ' . $httpCode : curl_error($ch);
            $minilog = new MiniLog();
            $minilog->alert($error . ' - ' . $url);

            curl_close($ch);
            return 'ERROR';
        }

        return file_get_contents($url);
    }

    /**
     * Alternative function when followlocation fails.
     * 
     * @param resource $ch
     * @param int      $redirects
     * 
     * @return string
     */
    private function curlRedirectExec(&$ch, &$redirects)
    {
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        switch ($httpCode) {
            case 301:
            case 302:
            case 303:
                list($header) = explode("\r\n\r\n", $data, 2);
                $matches = [];
                if (1 !== preg_match("/(Location:|URI:)[^(\n)]*/", $header, $matches)) {
                    break;
                }

                $url = trim(str_replace($matches[1], "", $matches[0]));
                $url_parsed = parse_url($url);
                if (isset($url_parsed)) {
                    curl_setopt($ch, CURLOPT_URL, $url);
                    $redirects++;
                    return $this->curlRedirectExec($ch, $redirects);
                }
        }

        if (empty($data)) {
            curl_close($ch);
            return 'ERROR';
        }

        list(, $body) = explode("\r\n\r\n", $data, 2);
        curl_close($ch);
        return $body;
    }

    /**
     * Downloads file from selected url.
     * 
     * @param string $url
     * @param string $filename
     * @param int    $timeout
     * 
     * @return bool
     */
    public function download(string $url, string $filename, int $timeout = self::TIMEOUT): bool
    {
        try {
            $data = $this->getContents($url, $timeout);
            if ($data && $data != 'ERROR' && file_put_contents($filename, $data) !== FALSE) {
                return true;
            }
        } catch (Exception $exc) {
            /// nothing to do
        }

        return false;
    }
}
