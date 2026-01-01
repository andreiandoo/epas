<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>We Miss You!</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            color: #fff;
            margin: 0;
            font-size: 28px;
        }
        .header p {
            color: rgba(255,255,255,0.9);
            margin: 10px 0 0;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 25px;
        }
        .offer-box {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin: 25px 0;
        }
        .offer-box h2 {
            color: #fff;
            margin: 0 0 10px;
            font-size: 32px;
        }
        .offer-box p {
            color: rgba(255,255,255,0.9);
            margin: 0;
        }
        .offer-code {
            display: inline-block;
            background: #fff;
            color: #764ba2;
            font-size: 20px;
            font-weight: bold;
            padding: 10px 25px;
            border-radius: 8px;
            margin-top: 15px;
            letter-spacing: 2px;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
        }
        .recommendations {
            margin: 30px 0;
        }
        .recommendations h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        .event-card {
            display: flex;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .event-card img {
            width: 120px;
            height: 80px;
            object-fit: cover;
        }
        .event-card-content {
            padding: 10px 15px;
            flex: 1;
        }
        .event-card-title {
            font-weight: bold;
            color: #333;
            margin: 0 0 5px;
        }
        .event-card-meta {
            color: #666;
            font-size: 13px;
        }
        .footer {
            background: #f8f9fa;
            padding: 25px 30px;
            text-align: center;
            font-size: 13px;
            color: #666;
        }
        .footer a {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $tenant->name ?? 'Events' }}</h1>
            <p>
                @if($tier === 'early_warning')
                    It's been a while since we've seen you!
                @elseif($tier === 'gentle_nudge')
                    We've got something special for you
                @elseif($tier === 'win_back')
                    We really miss you!
                @else
                    We'd love to see you again
                @endif
            </p>
        </div>

        <div class="content">
            <p class="greeting">
                Hi {{ $customer->first_name ?? 'there' }},
            </p>

            <div class="message">
                @if($tier === 'early_warning')
                    <p>We noticed you haven't visited us in a while, and we wanted to reach out. There are some amazing events coming up that we think you'll love!</p>
                @elseif($tier === 'gentle_nudge')
                    <p>It's been over two months since your last visit. We've been busy adding incredible new events, and we wanted to make sure you don't miss out.</p>
                @elseif($tier === 'win_back')
                    <p>We really miss having you at our events! To show you how much we appreciate you, here's a special offer just for you:</p>
                @else
                    <p>It's been too long! We value you as a customer and want to give you one more reason to come back:</p>
                @endif
            </div>

            @if($offer && isset($offer['discount_percent']) && $offer['discount_percent'] > 0)
                <div class="offer-box">
                    <h2>{{ $offer['discount_percent'] }}% OFF</h2>
                    <p>{{ $offer['headline'] ?? 'Your exclusive discount' }}</p>
                    @if(isset($offer['code']))
                        <div class="offer-code">{{ $offer['code'] }}</div>
                    @endif
                </div>
            @endif

            <div style="text-align: center;">
                <a href="{{ $tenant->domain ? 'https://' . $tenant->domain : '#' }}" class="cta-button">
                    {{ $offer['cta'] ?? 'Browse Events' }}
                </a>
            </div>

            @if(!empty($recommendations))
                <div class="recommendations">
                    <h3>Events We Think You'll Love</h3>

                    @foreach(array_slice($recommendations, 0, 3) as $rec)
                        @php $event = $rec['event'] ?? null; @endphp
                        @if($event)
                            <div class="event-card">
                                <img src="{{ $event->poster_url ?? $event->image_url ?? 'https://placehold.co/120x80?text=Event' }}"
                                     alt="{{ $event->name ?? $event->title ?? 'Event' }}">
                                <div class="event-card-content">
                                    <p class="event-card-title">{{ $event->name ?? $event->title ?? 'Exciting Event' }}</p>
                                    <p class="event-card-meta">
                                        @if($event->start_date ?? $event->starts_at ?? null)
                                            {{ \Carbon\Carbon::parse($event->start_date ?? $event->starts_at)->format('M d, Y') }}
                                        @endif
                                        @if($event->venue_name ?? optional($event->venue)->name ?? null)
                                            • {{ $event->venue_name ?? optional($event->venue)->name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            <p style="margin-top: 30px;">
                We hope to see you soon!<br>
                The {{ $tenant->name ?? 'Events' }} Team
            </p>
        </div>

        <div class="footer">
            <p>
                You're receiving this email because you're a valued customer of {{ $tenant->name ?? 'our platform' }}.
            </p>
            <p>
                <a href="#">Unsubscribe</a> |
                <a href="#">Update Preferences</a> |
                <a href="#">Privacy Policy</a>
            </p>
            <p style="font-size: 11px; color: #999; margin-top: 15px;">
                Campaign ID: {{ $campaignId ?? 'N/A' }}
            </p>
        </div>
    </div>
</body>
</html>
