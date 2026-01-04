<?php

declare(strict_types=1);

namespace Conduit\Queue\Exceptions;

/**
 * Max Attempts Exceeded Exception
 * 
 * Thrown when a job exceeds its maximum retry attempts
 */
class MaxAttemptsExceededException extends QueueException
{
    //
}
