<?php

namespace DexPaprika\Tests\Exception;

use DexPaprika\Exception\DexPaprikaApiException;
use PHPUnit\Framework\TestCase;

/**
 * Typed accessors for the fields the API sends on a "plan_required" error,
 * such as the 403 a keyless caller gets paging a search endpoint past its
 * first page: {"error": "plan_required", "required_tier": "free",
 * "param": "cursor", ...}. The accessors live on the base exception, not a
 * new subclass, since every typed exception (ClientException included)
 * already carries errorData through it.
 */
class DexPaprikaApiExceptionTest extends TestCase
{
    public function testAccessorsReadFromErrorData(): void
    {
        $e = new DexPaprikaApiException('message', 403, [
            'error' => 'plan_required',
            'tier' => 'keyless',
            'required_tier' => 'free',
            'param' => 'cursor',
            'message' => 'Search results beyond the first page require an API key.',
        ]);

        $this->assertSame('plan_required', $e->getErrorCode());
        $this->assertSame('free', $e->getRequiredTier());
        $this->assertSame('cursor', $e->getParam());
    }

    public function testAccessorsReturnNullWhenErrorDataAbsent(): void
    {
        $e = new DexPaprikaApiException('message', 403, null);

        $this->assertNull($e->getErrorCode());
        $this->assertNull($e->getRequiredTier());
        $this->assertNull($e->getParam());
    }

    public function testAccessorsReturnNullWhenFieldsMissing(): void
    {
        $e = new DexPaprikaApiException('message', 400, ['message' => 'something else']);

        $this->assertNull($e->getErrorCode());
        $this->assertNull($e->getRequiredTier());
        $this->assertNull($e->getParam());
    }

    public function testErrorCodeNullForTheLegacyErrorObjectShape(): void
    {
        // The 5xx edge case sends {"error": {"code": "...", "message": "..."}}.
        // getErrorCode() only reports a string "error", so this is null, not
        // the nested code: the legacy shape has no flat code to report.
        $e = new DexPaprikaApiException('message', 500, [
            'error' => ['code' => 'internal', 'message' => 'boom'],
        ]);

        $this->assertNull($e->getErrorCode());
    }

    public function testSetErrorDataDoesNotRetroactivelyPopulateAccessors(): void
    {
        $e = new DexPaprikaApiException('message', 403);
        $this->assertNull($e->getRequiredTier());

        $e->setErrorData(['required_tier' => 'free']);

        $this->assertSame('free', $e->getRequiredTier());
    }
}
