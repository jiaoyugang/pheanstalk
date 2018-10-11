<?php

namespace Pheanstalk;

use Pheanstalk\Contract\ResponseInterface;
use Pheanstalk\Contract\ResponseParserInterface;
use Pheanstalk\Response;
use Pheanstalk\Response\ArrayResponse;

/**
 * A response parser for commands that return a subset of YAML.
 *
 * Expected response is 'OK', 'NOT_FOUND' response is also handled.
 * Parser expects either a YAML list or dictionary, depending on mode.
 *
 * @author  Paul Annesley
 * @package Pheanstalk
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class YamlResponseParser implements ResponseParserInterface
{
    const MODE_LIST = 'list';
    const MODE_DICT = 'dict';

    private $mode;

    /**
     * @param string $mode self::MODE_*
     */
    public function __construct(string $mode)
    {
        if (!in_array($mode, [self::MODE_DICT, self::MODE_LIST])) {
            throw new \InvalidArgumentException('Invalid mode');
        }
        $this->mode = $mode;
    }

    public function parseResponse(string $responseLine, ?string $responseData): ArrayResponse
    {
        if ($responseLine == ResponseInterface::RESPONSE_NOT_FOUND) {
            throw new Exception\ServerException(sprintf(
                'Server reported %s',
                $responseLine
            ));
        }

        if (!preg_match('#^OK \d+$#', $responseLine)) {
            throw new Exception\ServerException(sprintf(
                'Unhandled response: "%s"',
                $responseLine
            ));
        }

        $dataLines = preg_split("#[\r\n]+#", rtrim($responseData));
        if (isset($dataLines[0]) && $dataLines[0] == '---') {
            array_shift($dataLines); // discard header line
        }

        $data = array_map([$this, 'mapYamlList'], $dataLines);

        if ($this->mode == self::MODE_DICT) {
            // TODO: do this better.
            $array = [];
            foreach ($data as $line) {
                if (!preg_match('#(\S+):\s*(.*)#', $line, $matches)) {
                    throw new Exception("YAML parse error for line: $line");
                }

                list(, $key, $value) = $matches;

                $array[$key] = $value;
            }
            $data = $array;
        }

        return new ArrayResponse('OK', $data);
    }

    /**
     * Callback for array_map to process YAML lines.
     *
     * @param string $line
     *
     * @return string
     */
    private function mapYamlList(string $line):string
    {
        return ltrim($line, '- ');
    }
}
