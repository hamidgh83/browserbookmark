<?php
namespace ZendLib\NetscapeBookmark;

interface SanitizerInterface
{
    public function sanitize ($str);

    public function sanitizeTagString ($tagString);
}