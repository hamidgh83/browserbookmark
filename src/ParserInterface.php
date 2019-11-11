<?php
namespace ZendLib\NetscapeBookmar;

interface ParserInterface
{
    const REGEX_SUBFOLDER          = '/^<h\d.*>(.*)<\/h\d>/i';
    const REGEX_HEADER_ENDED       = '/^<\/DL>/i';
    const REGEX_SHORTCUT_STARTED   = '/<a/i';
    const REGEX_SHORTCUT_URL       = '/href="(.*?)"/i';
    const REGEX_SHORTCUT_TITLE     = '/<a.*>(.*?)<\/a>/i';
    const REGEX_DESCRIPTION        = '/(description|note)="(.*?)"/i';
    const REGEX_CONTENT            = '/<dd>(.*?)$/i';
    const REGEX_TAGS               = '/(tags?|labels?|folders?)="(.*?)"/i';
    const REGEX_DATE               = '/add_date="(.*?)"/i';
    const REGEX_VISIBILITY_PUBLIC  = '/(public|published|pub)="(.*?)"/i';
    const REGEX_VISIBILITY_PRIVATE = '/(private|shared)="(.*?)"/i';

    public function readFile (string $filePath);

    public function parse (): array;
}