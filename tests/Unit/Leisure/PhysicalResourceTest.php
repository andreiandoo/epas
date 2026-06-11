<?php

namespace Tests\Unit\Leisure;

use App\Models\Leisure\PhysicalResource;
use PHPUnit\Framework\TestCase;

class PhysicalResourceTest extends TestCase
{
    public function test_generate_qr_code_uses_uppercased_prefix(): void
    {
        $code = PhysicalResource::generateQrCode(42, 'boat');
        $this->assertStringStartsWith('BOAT-42-', $code);
        // 6 random suffix chars + "BOAT-42-" prefix
        $this->assertSame(6 + strlen('BOAT-42-'), strlen($code));
    }

    public function test_generate_qr_code_strips_non_alphanumeric_chars(): void
    {
        $code = PhysicalResource::generateQrCode(1, 'go-kart!');
        $this->assertStringStartsWith('GOKART-1-', $code);
    }

    public function test_generate_qr_code_falls_back_when_empty_type(): void
    {
        $code = PhysicalResource::generateQrCode(1, '!!!');
        $this->assertStringStartsWith('RES-1-', $code);
    }

    public function test_generate_qr_code_is_unique_across_calls(): void
    {
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = PhysicalResource::generateQrCode(1, 'kayak');
        }
        $this->assertSame(count($codes), count(array_unique($codes)),
            'generateQrCode should be effectively unique across many calls');
    }

    public function test_is_allowed_for_ticket_type_when_empty_whitelist(): void
    {
        $r = new PhysicalResource();
        $r->setRawAttributes(['linked_ticket_type_ids' => null], true);
        $this->assertTrue($r->isAllowedForTicketType(999));

        $r->setRawAttributes(['linked_ticket_type_ids' => '[]'], true);
        $this->assertTrue($r->isAllowedForTicketType(999));
    }

    public function test_is_allowed_for_ticket_type_with_whitelist(): void
    {
        $r = new PhysicalResource();
        $r->setRawAttributes(['linked_ticket_type_ids' => json_encode([10, 20, 30])], true);

        $this->assertTrue($r->isAllowedForTicketType(20));
        $this->assertFalse($r->isAllowedForTicketType(40));
    }
}
