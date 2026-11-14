<?php

namespace App\Mcp\Support;

use RuntimeException;

/**
 * Thrown from the download progress callback to abort an oversized download.
 *
 * @internal
 */
final class FileTooLargeException extends RuntimeException {}
