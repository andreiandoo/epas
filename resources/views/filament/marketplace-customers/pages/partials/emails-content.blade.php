            <x-filament::section>
                <x-slot name="heading">Istoric Email-uri ({{ count($emailLogs) }})</x-slot>
                @if(!empty($emailLogs))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Subiect</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Template</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Trimis la</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Creat la</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($emailLogs as $log)
                                    <tr>
                                        <td class="max-w-xs px-3 py-2 text-gray-800 truncate dark:text-gray-200">{{ $log->subject }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $log->template_name ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                                {{ match($log->status) {
                                                    'sent' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                                    'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                                    default => 'bg-gray-100 text-gray-700',
                                                } }}">{{ ucfirst($log->status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $log->sent_at ? \Carbon\Carbon::parse($log->sent_at)->format('d.m.Y H:i') : '-' }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($log->created_at)->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu s-au trimis email-uri către acest client.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 4: CUSTOMER INSIGHTS
