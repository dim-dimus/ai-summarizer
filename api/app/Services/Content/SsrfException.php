<?php

namespace App\Services\Content;

use RuntimeException;

/** Thrown when a URL fails SSRF validation. */
class SsrfException extends RuntimeException
{
}
