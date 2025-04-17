<?php

declare(strict_types=1);

namespace DexPaprika\Exception;

/**
 * Exception thrown when API rate limit is exceeded (429 errors)
 */
class RateLimitException extends DexPaprikaApiException
{
} 