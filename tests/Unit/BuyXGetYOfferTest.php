<?php

namespace Tests\Unit;

use App\Models\TicketType;
use App\Services\Marketplace\BuyXGetYOffer;
use PHPUnit\Framework\TestCase;

class BuyXGetYOfferTest extends TestCase
{
    private function type(array $attrs): TicketType
    {
        $tt = new TicketType();
        $tt->forceFill(array_merge(['price_cents' => 10000, 'meta' => []], $attrs));

        return $tt;
    }

    public function test_free_tickets_on_top_of_paid_quantity(): void
    {
        $rule = ['buy' => 2, 'get' => 1];
        $this->assertSame(0, BuyXGetYOffer::freeFor($rule, 1));
        $this->assertSame(1, BuyXGetYOffer::freeFor($rule, 2));
        $this->assertSame(1, BuyXGetYOffer::freeFor($rule, 3));
        $this->assertSame(2, BuyXGetYOffer::freeFor($rule, 4));
        $this->assertSame(3, BuyXGetYOffer::freeFor(['buy' => 3, 'get' => 3], 5) + 0);
    }

    public function test_seated_split_last_y_of_each_group_are_free(): void
    {
        $rule = ['buy' => 2, 'get' => 1];
        $this->assertSame([[0, 1], [2]], BuyXGetYOffer::splitSeats($rule, 3));
        $this->assertSame([[0, 1], []], BuyXGetYOffer::splitSeats($rule, 2));
        $this->assertSame([[0, 1, 3], [2]], BuyXGetYOffer::splitSeats($rule, 4));
        $this->assertSame([[0, 1, 3, 4], [2, 5]], BuyXGetYOffer::splitSeats($rule, 6));
        // Free seats never exceed what the paid ones earn
        foreach (range(1, 20) as $n) {
            [$paid, $free] = BuyXGetYOffer::splitSeats($rule, $n);
            $this->assertLessThanOrEqual(BuyXGetYOffer::freeFor($rule, count($paid)), count($free));
        }
    }

    public function test_rule_needs_valid_quantities_and_a_paid_type(): void
    {
        $rule = ['rule_type' => 'buy_x_get_y', 'buy_qty' => '2', 'get_qty' => '1'];
        $this->assertSame(['buy' => 2, 'get' => 1], BuyXGetYOffer::ruleFor($this->type(['bulk_discounts' => [$rule]])));

        $this->assertNull(BuyXGetYOffer::ruleFor($this->type(['bulk_discounts' => [['rule_type' => 'buy_x_get_y', 'buy_qty' => null, 'get_qty' => 1]]])));
        $this->assertNull(BuyXGetYOffer::ruleFor($this->type(['bulk_discounts' => [['rule_type' => 'bundle_price', 'min_qty' => 2]]])));
        $this->assertNull(BuyXGetYOffer::ruleFor($this->type(['bulk_discounts' => [$rule], 'price_cents' => 0])));
        $this->assertNull(BuyXGetYOffer::ruleFor($this->type(['bulk_discounts' => [$rule], 'meta' => ['is_invitation' => true]])));
        $this->assertNull(BuyXGetYOffer::ruleFor(null));
    }

    public function test_free_ticket_label(): void
    {
        $this->assertSame('Invitatie – ofertă 2+1 Presale', BuyXGetYOffer::freeTicketLabel(['buy' => 2, 'get' => 1], 'Presale'));
    }
}
