<?php

namespace ZendLib\NetscapeBookmark;

class Parser implements ParserInterface
{
    protected $keepNestedTags = true;

    protected $defaultTags = [];

    protected $defaultPub = '0';

    protected $normalizeDates = true;

    protected $dateRange = '30 years';

    protected $document;

    protected $filePath;

    protected $sanitizer;

    protected $items;


    public function __construct(Sanitizer $sanitizer, $options = [])
    {
        $this->sanitizer = $sanitizer;

        if ($options) {
            $this->defaultTags    = isset($options['defaultTags']) ? $options['defaultTags'] : [];
            $this->keepNestedTags = isset($options['keppNestedTags']) ? $options['keepNestedTags'] : true;
            $this->defaultPub     = isset($options['defaultPub']) ? $options['defaultPub'] : '0';
            $this->normalizeDates = isset($options['normalizeDates']) ? $options['normalizeDates'] : true;
            $this->dateRange      = isset($options['dateRange']) ? $options['dateRange'] : '30 years';
        }
    }

    public function readFile(string $filePath)
    {
        if (!is_file($filePath)) {
            // TODO throw exception
        }

        $this->filePath = $filePath;
        $this->document = file_get_contents($filePath);

        return $this;
    }

    public function parse(): array
    {
        if (!$this->document) {
            throw new \InvalidArgumentException('There is no file content to be parsed. Make sure if a valid file has been provided.');
        }

        return $this->parseBookmarkString($this->document);
    }

    protected function parseBookmarkString($bookmarkString)
    {
        $i           = 0;
        $folderNames = [];
        $lines       = explode("\n", $this->sanitizer->sanitize($bookmarkString));
        
        foreach ($lines as $line => $content) {
            if ($this->isMatch($content, ParserInterface::REGEX_SUBFOLDER, $m1)) {
                $tag           = $this->sanitizer->sanitizeTagString($m1[1]);
                $folderNames[] = $tag;
                // $this->logger->debug('[#' . $line . '] Header found: ' . $tag);
                continue;
            } elseif ($this->isMatch($content, ParserInterface::REGEX_HEADER_ENDED)) {
                $tag = array_pop($folderNames);
                // $this->logger->debug('[#' . $line . '] Header ended: ' . $tag);
                continue;
            }

            /* A Link found! Extract link properties. */
            if ($this->isMatch($content, ParserInterface::REGEX_SHORTCUT_STARTED, $m2)) {
                $this->items[$i]         = $this->extractLinkProperties($content);
                $this->items[$i]['tags'] = $this->extractTags($content, $folderNames);
                // $this->logger->debug('[#' . $line . '] Tag list: '. $this->items[$i]['tags']);

                $this->items[$i]['time'] = $this->extractDate($content);
                // $this->logger->debug('[#' . $line . '] Date: '. $this->items[$i]['time']);

                $this->items[$i]['pub'] = $this->extractVisibility($content);
                // $this->logger->debug('[#' . $line . '] Visibility: '. ($this->items[$i]['pub'] ? 'public' : 'private'));

                $i++;
            }
        }

        ksort($this->items);
        // $this->logger->info('File parsing ended');
        return $this->items;
    }

    private function extractLinkProperties($content): array
    {
        $items = [];

        if ($this->isMatch($content, ParserInterface::REGEX_SHORTCUT_URL, $m3)) {
            $items['uri'] = $m3[1];
        // $this->logger->debug('[#' . $line . '] URL found: ' . $m3[1]);
        } else {
            $items['uri'] = '';
            // $this->logger->debug('[#' . $line . '] Empty URL');
        }

        if ($this->isMatch($content, ParserInterface::REGEX_SHORTCUT_TITLE, $m4)) {
            $items['title'] = $m4[1];
        // $this->logger->debug('[#' . $line . '] Title found: ' . $m4[1]);
        } else {
            $items['title'] = 'untitled';
            // $this->logger->debug('[#' . $line . '] Empty title');
        }

        if ($this->isMatch($content, ParserInterface::REGEX_DESCRIPTION, $m5)) {
            $items['note'] = $m5[2];
        // $this->logger->debug('[#' . $line . '] Content found: ' . substr($m5[2], 0, 50) . '...');
        } elseif ($this->isMatch($content, ParserInterface::REGEX_CONTENT, $m6)) {
            $items['note'] = str_replace('<br>', "\n", $m6[1]);
        // $this->logger->debug('[#' . $line . '] Content found: ' . substr($m6[1], 0, 50) . '...');
        } else {
            $items['note'] = '';
            // $this->logger->debug('[#' . $line . '] Empty content');
        }

        return $items;
    }

    private function extractTags($content, $nestedTags = []): array
    {
        $tags = [];
        if ($this->defaultTags) {
            $tags = array_merge($tags, $this->defaultTags);
        }

        if ($this->keepNestedTags) {
            $tags = array_merge($tags, $nestedTags);
        }

        if ($this->isMatch($content, ParserInterface::REGEX_TAGS, $m7)) {
            $tags = array_merge($tags, explode(' ', strtr($m7[2], ',', ' ')));
        }

        return $tags;
    }

    private function extractDate($content)
    {
        if ($this->isMatch($content, ParserInterface::REGEX_DATE, $m8)) {
            return $this->parseDate($m8[1]);
        }

        return time();
    }

    private function parseDate($date)
    {
        if (strtotime('@'.$date)) {
            // Unix timestamp
            if ($this->normalizeDates) {
                $date = $this->normalizeDate($date);
            }
            return strtotime('@'.$date);
        } elseif (strtotime($date)) {
            // Attempt to parse a known compound date/time format
            return strtotime($date);
        }

        // Current date & time
        return time();
    }

    private function normalizeDate($epoch)
    {
        $date = new \DateTime('@'.$epoch);
        $maxDate = new \DateTime('+'.$this->dateRange);

        for ($i = 1; $date > $maxDate; $i++) {
            // trim the provided date until it falls within the expected range
            $date = new \DateTime('@'.substr($epoch, 0, strlen($epoch) - $i));
        }

        return $date->getTimestamp();
    }

    private function extractVisibility($content)
    {
        if ($this->isMatch($content, ParserInterface::REGEX_VISIBILITY_PUBLIC, $m9)) {
            return $this->parseBoolean($m9[2], false) ? 1 : 0;
        } elseif ($this->isMatch($content, ParserInterface::REGEX_VISIBILITY_PRIVATE, $m10)) {
            return $this->parseBoolean($m10[2], true) ? 0 : 1;
        }
          
        return $this->defaultPub;
    }

    protected function isMatch($subject, $pattern, &$match = null): bool
    {
        return preg_match($pattern, $subject, $match);
    }
}
