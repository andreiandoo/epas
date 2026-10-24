<?php

namespace App\Support\Tenant;

use App\Filament\Tenant\Pages\Microservices;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantNewsletterSubscriber;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporturile de date ale clienților din panoul tenantului. Merg doar cu
 * microserviciul „Export clienți” activ, iar fiecare export rămâne în jurnal.
 */
class TenantDataExport
{
    public const MICROSERVICE = 'customers-export';

    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];

    public static function allowed(?Tenant $tenant): bool
    {
        return $tenant !== null && $tenant->hasMicroservice(self::MICROSERVICE);
    }

    /** Butonul din panou: fără modul, explică de unde se activează. */
    public static function action(string $label, callable $export): Action
    {
        return Action::make('export')
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(function () use ($export) {
                $tenant = auth()->user()?->tenant;
                if (! self::allowed($tenant)) {
                    Notification::make()
                        ->warning()
                        ->title('Exportul merge cu modulul „Export clienți”')
                        ->body('Îl pornești din Microservicii. Clienții rămân oricum în cont, îi vezi aici oricând.')
                        ->actions([Action::make('open')->label('Vezi Microservicii')->url(Microservices::getUrl())])
                        ->persistent()
                        ->send();

                    return null;
                }

                return $export($tenant);
            });
    }

    public static function customers(Tenant $tenant): StreamedResponse
    {
        $subscribed = TenantNewsletterSubscriber::where('tenant_id', $tenant->id)->where('status', 'subscribed')
            ->pluck('email')->map(fn ($e) => strtolower((string) $e))->flip();

        return self::stream($tenant, 'clienti', ['nume', 'email', 'telefon', 'oras', 'comenzi', 'total_cheltuit', 'prima_comanda', 'ultima_comanda', 'abonat_newsletter'],
            function (callable $write) use ($tenant, $subscribed) {
                Customer::where('tenant_id', $tenant->id)->orderBy('id')->chunk(500, function ($customers) use ($tenant, $subscribed, $write) {
                    $stats = Order::where('tenant_id', $tenant->id)->whereIn('customer_id', $customers->pluck('id'))
                        ->whereIn('status', self::PAID_STATUSES)
                        ->selectRaw('customer_id, count(*) as orders, sum(total) as spent, min(created_at) as first_at, max(created_at) as last_at')
                        ->groupBy('customer_id')->get()->keyBy('customer_id');

                    foreach ($customers as $c) {
                        $s = $stats[$c->id] ?? null;
                        $write([
                            trim($c->full_name ?: ($c->first_name . ' ' . $c->last_name)),
                            $c->email,
                            $c->phone,
                            $c->city,
                            (int) ($s->orders ?? 0),
                            number_format((float) ($s->spent ?? 0), 2, '.', ''),
                            $s?->first_at ? substr((string) $s->first_at, 0, 10) : '',
                            $s?->last_at ? substr((string) $s->last_at, 0, 10) : '',
                            isset($subscribed[strtolower((string) $c->email)]) ? 'da' : 'nu',
                        ]);
                    }
                });
            });
    }

    public static function newsletterSubscribers(Tenant $tenant): StreamedResponse
    {
        return self::stream($tenant, 'abonati-newsletter', ['email', 'prenume', 'limba', 'sursa', 'stare', 'abonat_la', 'dezabonat_la'],
            function (callable $write) use ($tenant) {
                TenantNewsletterSubscriber::where('tenant_id', $tenant->id)->orderBy('id')->chunk(500, function ($rows) use ($write) {
                    foreach ($rows as $r) {
                        $write([$r->email, $r->first_name, $r->locale, $r->source, $r->status, $r->subscribed_at?->format('Y-m-d H:i'), $r->unsubscribed_at?->format('Y-m-d H:i')]);
                    }
                });
            });
    }

    private static function stream(Tenant $tenant, string $type, array $header, callable $rows): StreamedResponse
    {
        $user = auth()->user();
        $ip = request()->ip();

        return response()->streamDownload(function () use ($tenant, $type, $header, $rows, $user, $ip) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            $count = 0;
            $rows(function (array $row) use ($out, &$count) {
                fputcsv($out, $row);
                $count++;
            });
            fclose($out);

            // Datele ies din platformă: rămâne urma cine, când și câte rânduri
            activity('data-export')
                ->causedBy($user)
                ->performedOn($tenant)
                ->withProperties(['type' => $type, 'rows' => $count, 'ip' => $ip])
                ->log('Export ' . $type);
        }, $type . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
