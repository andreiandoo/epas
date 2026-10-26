<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Datele firmei pentru termeni și politica de confidențialitate, luate din Setări,
 * ca documentele să arate mereu datele actuale.
 */
class LegalInfo
{
    public static function company(): array
    {
        $s = Setting::current();

        return [
            'name' => $s->company_name ?: 'Tixello',
            'cui' => $s->cui,
            'reg_com' => $s->reg_com,
            'address' => trim(implode(', ', array_filter([$s->address, $s->city, $s->state, $s->country]))),
            'phone' => $s->phone,
            'email' => config('legal.support_email'),
            'privacy_email' => config('legal.privacy_email'),
            'payment_days' => (int) ($s->default_payment_terms_days ?: 5),
        ];
    }
}
