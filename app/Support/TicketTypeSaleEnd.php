<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Ce se intampla cu un tip de bilet dupa "Sfarsit reducere".
 *
 * sales_end_at opreste doar pretul promotional: biletul ramane la vanzare la
 * pretul intreg. Un bilet de tip presale trebuie sa se si inchida, adica sa
 * aiba active_until pe aceeasi data — pas pe care operatorul il uita usor
 * (evenimentul 4720: bilete "Presale" vandute la pretul categoriei finale).
 *
 * Formularul cere alegerea explicit prin campul virtual `after_sale_ends`;
 * nu exista coloana, alegerea se traduce in active_until la salvare.
 */
class TicketTypeSaleEnd
{
    public const FIELD = 'after_sale_ends';

    public const FULL_PRICE = 'full_price';

    public const STOP = 'stop';

    public static function options(): array
    {
        return [
            self::FULL_PRICE => 'Rămâne la vânzare la preț întreg',
            self::STOP => 'Se oprește vânzarea',
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::FULL_PRICE => 'După data de sfârșit, biletul se vinde în continuare, la prețul fără reducere.',
            self::STOP => 'Bilet de tip presale: după data de sfârșit nu se mai poate cumpăra. „Activ până la” se completează automat cu aceeași dată.',
        ];
    }

    /**
     * Alegerea dedusa din datele salvate (la incarcarea formularului).
     */
    public static function resolve(array $state): ?string
    {
        $end = self::parse($state['sales_end_at'] ?? null);
        if (!$end) {
            return null;
        }
        $until = self::parse($state['active_until'] ?? null);

        return ($until && $until->lte($end)) ? self::STOP : self::FULL_PRICE;
    }

    /**
     * Traduce alegerea in active_until, inainte de salvarea tipului de bilet.
     */
    public static function apply(array $data): array
    {
        $choice = $data[self::FIELD] ?? null;
        unset($data[self::FIELD]);

        $end = $data['sales_end_at'] ?? null;
        if (!$choice || !self::parse($end)) {
            return $data;
        }

        if ($choice === self::STOP) {
            $until = self::parse($data['active_until'] ?? null);
            if (!$until || $until->gt(self::parse($end))) {
                $data['active_until'] = $end;
            }
        } elseif ($choice === self::FULL_PRICE) {
            // Sterge doar un active_until pus de varianta "se opreste vanzarea".
            $until = self::parse($data['active_until'] ?? null);
            if ($until && $until->equalTo(self::parse($end))) {
                $data['active_until'] = null;
            }
        }

        return $data;
    }

    /**
     * Eticheta pentru antetul tipului de bilet: ce pret are acum si ce urmeaza
     * dupa expirarea reducerii. Gol daca nu exista o reducere cu data de sfarsit
     * in viitor.
     */
    public static function badge(array $state): string
    {
        $end = self::parse($state['sales_end_at'] ?? null);
        $sale = $state['price'] ?? null;
        if (!$end || $end->isPast() || $sale === null || $sale === '' || (float) $sale <= 0) {
            return '';
        }

        $choice = $state[self::FIELD] ?? self::resolve($state);
        $text = self::money($sale) . ' până la ' . $end->format('d.m.Y H:i') . ', apoi ';
        if ($choice === self::STOP) {
            $text .= 'se închide';
            $style = 'color:#1d4ed8;background:#eff6ff';
        } else {
            $text .= self::money($state['price_max'] ?? 0);
            $style = 'color:#b45309;background:#fffbeb';
        }

        return '<span style="font-size:10px;font-weight:600;' . $style . ';padding:1px 6px;border-radius:4px;margin-left:4px;">' . e($text) . '</span>';
    }

    private static function money($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',');
    }

    private static function parse($value): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value, 'Europe/Bucharest');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
