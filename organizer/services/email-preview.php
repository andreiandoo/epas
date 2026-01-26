<?php
/**
 * Email Campaign Preview Page
 * Shows a preview of the email that will be sent
 * URL: /organizator/services/email-preview?order={order_id}
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
$orderId = $_GET['order'] ?? '';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Previzualizare Email - Ambilet</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            min-height: 100vh;
        }
        .preview-header {
            background: #fff;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .preview-header h1 {
            font-size: 18px;
            color: #1a1a1a;
        }
        .preview-header .badge {
            background: #f59e0b;
            color: #fff;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            padding: 30px;
            text-align: center;
        }
        .email-header img.logo {
            height: 40px;
            margin-bottom: 10px;
        }
        .email-header h2 {
            color: #fff;
            font-size: 24px;
            margin: 0;
        }
        .email-body {
            padding: 30px;
        }
        .event-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .event-title {
            font-size: 24px;
            color: #1a1a1a;
            margin-bottom: 16px;
            text-align: center;
        }
        .event-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .event-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .event-detail-row:last-child {
            border-bottom: none;
        }
        .event-detail-label {
            color: #6b7280;
            font-size: 14px;
        }
        .event-detail-value {
            color: #1a1a1a;
            font-weight: 600;
            font-size: 14px;
        }
        .event-description {
            color: #4b5563;
            line-height: 1.6;
            text-align: center;
            margin-bottom: 24px;
        }
        .cta-button {
            display: block;
            width: 100%;
            max-width: 300px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            text-align: center;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
        }
        .email-footer {
            background: #f8f9fa;
            padding: 24px;
            text-align: center;
        }
        .email-footer p {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.6;
        }
        .email-footer a {
            color: #6366f1;
            text-decoration: none;
        }
        .social-links {
            margin-bottom: 16px;
        }
        .social-links a {
            display: inline-block;
            width: 32px;
            height: 32px;
            background: #e5e7eb;
            border-radius: 50%;
            margin: 0 4px;
            line-height: 32px;
            color: #6b7280;
            text-decoration: none;
        }
        .loading {
            text-align: center;
            padding: 60px 20px;
        }
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .error {
            text-align: center;
            padding: 60px 20px;
            color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="preview-header">
        <h1>Previzualizare Email</h1>
        <span class="badge">Preview</span>
    </div>

    <div id="preview-content">
        <div class="loading">
            <div class="loading-spinner"></div>
            <p>Se incarca previzualizarea...</p>
        </div>
    </div>

    <script src="/assets/js/config.js"></script>
    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/utils.js"></script>
    <script>
        const orderId = '<?php echo htmlspecialchars($orderId); ?>';

        async function loadPreview() {
            if (!orderId) {
                showError('ID-ul comenzii lipseste');
                return;
            }

            try {
                const response = await AmbiletAPI.get(`/organizer/services/orders/${orderId}`);
                if (response.success && response.data.order) {
                    renderPreview(response.data.order);
                } else {
                    showError('Comanda nu a fost gasita');
                }
            } catch (e) {
                showError('Eroare la incarcarea comenzii');
            }
        }

        function renderPreview(order) {
            if (order.service_type !== 'email') {
                showError('Aceasta comanda nu este pentru email marketing');
                return;
            }

            const event = order.event || {};
            const config = order.config || {};

            document.getElementById('preview-content').innerHTML = `
                <div class="email-container">
                    <div class="email-header">
                        <h2>Ambilet</h2>
                    </div>

                    <div class="email-body">
                        <img src="${event.image || '/assets/images/default-event.png'}" alt="${event.title || 'Eveniment'}" class="event-image">

                        <h1 class="event-title">${event.title || 'Titlu Eveniment'}</h1>

                        <div class="event-details">
                            <div class="event-detail-row">
                                <span class="event-detail-label">Data</span>
                                <span class="event-detail-value">${event.date ? formatDate(event.date) : 'TBA'}</span>
                            </div>
                            <div class="event-detail-row">
                                <span class="event-detail-label">Ora</span>
                                <span class="event-detail-value">${event.time || '20:00'}</span>
                            </div>
                            <div class="event-detail-row">
                                <span class="event-detail-label">Locatie</span>
                                <span class="event-detail-value">${event.venue || 'TBA'}</span>
                            </div>
                            <div class="event-detail-row">
                                <span class="event-detail-label">Oras</span>
                                <span class="event-detail-value">${event.city || ''}</span>
                            </div>
                        </div>

                        <p class="event-description">
                            Nu rata acest eveniment spectaculos! Asigura-ti biletele acum si fii parte dintr-o experienta de neuitat.
                            Locurile sunt limitate, asa ca nu astepta pana in ultimul moment.
                        </p>

                        <a href="#" class="cta-button">
                            Cumpara Bilete Acum
                        </a>
                    </div>

                    <div class="email-footer">
                        <div class="social-links">
                            <a href="#">f</a>
                            <a href="#">in</a>
                            <a href="#">ig</a>
                        </div>
                        <p>
                            Ai primit acest email pentru ca esti abonat la newsletter-ul Ambilet.<br>
                            <a href="#">Dezabonare</a> | <a href="#">Preferinte email</a>
                        </p>
                        <p style="margin-top: 12px;">
                            &copy; ${new Date().getFullYear()} Ambilet. Toate drepturile rezervate.
                        </p>
                    </div>
                </div>
            `;
        }

        function showError(message) {
            document.getElementById('preview-content').innerHTML = `
                <div class="error">
                    <p>${message}</p>
                </div>
            `;
        }

        function formatDate(dateStr) {
            if (!dateStr) return 'TBA';
            const date = new Date(dateStr);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('ro-RO', options);
        }

        // Load on ready
        document.addEventListener('DOMContentLoaded', loadPreview);
    </script>
</body>
</html>
