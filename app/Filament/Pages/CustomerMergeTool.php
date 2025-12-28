<?php

namespace App\Filament\Pages;

use App\Models\Platform\CoreCustomer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class CustomerMergeTool extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected string $view = 'filament.pages.customer-merge-tool';

    protected static ?string $navigationLabel = 'Merge Customers';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 8;

    protected static ?string $title = 'Customer Merge Tool';

    public ?int $sourceCustomerId = null;
    public ?int $targetCustomerId = null;
    public ?array $sourceCustomer = null;
    public ?array $targetCustomer = null;
    public array $duplicateCandidates = [];
    public string $searchQuery = '';

    public function mount(): void
    {
        $this->loadDuplicateCandidates();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('sourceCustomerId')
                    ->label('Source Customer (will be merged)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => $this->searchCustomers($search))
                    ->getOptionLabelUsing(fn ($value) => $this->getCustomerLabel($value))
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->loadSourceCustomer($state)),

                Select::make('targetCustomerId')
                    ->label('Target Customer (will receive data)')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => $this->searchCustomers($search))
                    ->getOptionLabelUsing(fn ($value) => $this->getCustomerLabel($value))
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->loadTargetCustomer($state)),
            ]);
    }

    protected function searchCustomers(string $search): array
    {
        if (strlen($search) < 2) {
            return [];
        }

        $searchLower = strtolower($search);
        $searchPattern = '%' . $searchLower . '%';

        return CoreCustomer::notMerged()
            ->notAnonymized()
            ->where(function ($query) use ($searchPattern) {
                $query->whereRaw('LOWER(uuid) LIKE ?', [$searchPattern])
                    ->orWhere('email_hash', '=', hash('sha256', $searchPattern)) // Exact email match
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$searchPattern])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$searchPattern]);
            })
            ->limit(20)
            ->get()
            ->mapWithKeys(fn ($customer) => [
                $customer->id => $this->formatCustomerOption($customer),
            ])
            ->toArray();
    }

    protected function formatCustomerOption(CoreCustomer $customer): string
    {
        $email = $customer->email ?? 'No email';
        return "{$customer->getDisplayName()} ({$email}) - {$customer->total_orders} orders, \${$customer->total_spent}";
    }

    protected function getCustomerLabel(?int $customerId): ?string
    {
        if (!$customerId) return null;

        $customer = CoreCustomer::find($customerId);
        return $customer ? $this->formatCustomerOption($customer) : null;
    }

    public function loadSourceCustomer(?int $customerId): void
    {
        if (!$customerId) {
            $this->sourceCustomer = null;
            return;
        }

        $customer = CoreCustomer::find($customerId);
        $this->sourceCustomer = $customer ? $this->formatCustomerDetails($customer) : null;
    }

    public function loadTargetCustomer(?int $customerId): void
    {
        if (!$customerId) {
            $this->targetCustomer = null;
            return;
        }

        $customer = CoreCustomer::find($customerId);
        $this->targetCustomer = $customer ? $this->formatCustomerDetails($customer) : null;
    }

    protected function formatCustomerDetails(CoreCustomer $customer): array
    {
        return [
            'id' => $customer->id,
            'uuid' => $customer->uuid,
            'display_name' => $customer->getDisplayName(),
            'email' => $customer->email,
            'phone' => $customer->phone,
            'total_orders' => $customer->total_orders,
            'total_spent' => $customer->total_spent,
            'total_visits' => $customer->total_visits,
            'first_seen_at' => $customer->first_seen_at?->format('M j, Y'),
            'last_seen_at' => $customer->last_seen_at?->format('M j, Y'),
            'first_purchase_at' => $customer->first_purchase_at?->format('M j, Y'),
            'last_purchase_at' => $customer->last_purchase_at?->format('M j, Y'),
            'customer_segment' => $customer->customer_segment,
            'health_score' => $customer->health_score,
            'events_count' => $customer->events()->count(),
            'sessions_count' => $customer->sessions()->count(),
        ];
    }

    public function loadDuplicateCandidates(): void
    {
        // Find potential duplicates based on similar data
        $this->duplicateCandidates = CoreCustomer::notMerged()
            ->whereNotNull('email_hash')
            ->select('email_hash')
            ->groupBy('email_hash')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $customers = CoreCustomer::where('email_hash', $row->email_hash)
                    ->notMerged()
                    ->orderByDesc('total_orders')
                    ->get();

                return [
                    'email_hash' => $row->email_hash,
                    'count' => $customers->count(),
                    'customers' => $customers->map(fn ($c) => [
                        'id' => $c->id,
                        'display_name' => $c->getDisplayName(),
                        'email' => $c->email,
                        'total_orders' => $c->total_orders,
                        'total_spent' => $c->total_spent,
                        'first_seen_at' => $c->first_seen_at?->format('M j, Y'),
                    ])->toArray(),
                ];
            })
            ->toArray();
    }

    public function selectDuplicatePair(int $sourceId, int $targetId): void
    {
        $this->sourceCustomerId = $sourceId;
        $this->targetCustomerId = $targetId;
        $this->loadSourceCustomer($sourceId);
        $this->loadTargetCustomer($targetId);
    }

    public function swapCustomers(): void
    {
        $temp = $this->sourceCustomerId;
        $this->sourceCustomerId = $this->targetCustomerId;
        $this->targetCustomerId = $temp;

        $temp = $this->sourceCustomer;
        $this->sourceCustomer = $this->targetCustomer;
        $this->targetCustomer = $temp;
    }

    public function merge(): void
    {
        if (!$this->sourceCustomerId || !$this->targetCustomerId) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Please select both source and target customers.')
                ->send();
            return;
        }

        if ($this->sourceCustomerId === $this->targetCustomerId) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('Cannot merge a customer with itself.')
                ->send();
            return;
        }

        $source = CoreCustomer::find($this->sourceCustomerId);
        $target = CoreCustomer::find($this->targetCustomerId);

        if (!$source || !$target) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('One or both customers not found.')
                ->send();
            return;
        }

        try {
            $source->mergeInto($target);

            Notification::make()
                ->success()
                ->title('Customers Merged')
                ->body("Successfully merged {$source->getDisplayName()} into {$target->getDisplayName()}")
                ->send();

            // Reset form
            $this->sourceCustomerId = null;
            $this->targetCustomerId = null;
            $this->sourceCustomer = null;
            $this->targetCustomer = null;

            // Reload duplicate candidates
            $this->loadDuplicateCandidates();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Merge Failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function getMergePreview(): array
    {
        if (!$this->sourceCustomer || !$this->targetCustomer) {
            return [];
        }

        return [
            'total_orders' => ($this->sourceCustomer['total_orders'] ?? 0) + ($this->targetCustomer['total_orders'] ?? 0),
            'total_spent' => ($this->sourceCustomer['total_spent'] ?? 0) + ($this->targetCustomer['total_spent'] ?? 0),
            'total_visits' => ($this->sourceCustomer['total_visits'] ?? 0) + ($this->targetCustomer['total_visits'] ?? 0),
            'events_count' => ($this->sourceCustomer['events_count'] ?? 0) + ($this->targetCustomer['events_count'] ?? 0),
            'sessions_count' => ($this->sourceCustomer['sessions_count'] ?? 0) + ($this->targetCustomer['sessions_count'] ?? 0),
        ];
    }
}
