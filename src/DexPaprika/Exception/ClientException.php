<?php

declare(strict_types=1);

namespace DexPaprika\Exception;

/**
 * Exception thrown for client errors (4xx status codes not covered by specific exceptions)
 */
class ClientException extends DexPaprikaApiException
{
} 