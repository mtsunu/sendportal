<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/**
 * Thrown by ThrottledSesAdapter::send() when in-send rate retries or the Redis
 * limiter block are exhausted. Replaces the vendor trait's silent null return
 * (which reached resolveMessageId(Result) and produced a TypeError against the
 * string return type). Horizon (tries=3) is the single outer retry owner.
 */
final class SesSendThrottledException extends RuntimeException
{
}
