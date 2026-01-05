<?php
/**
 * Ambilet Email Service
 *
 * Uses Brevo (Sendinblue) API for sending transactional emails.
 * Documentation: https://developers.brevo.com/reference/sendtransacemail
 */

class AmbiletEmailService {
    private $apiKey;
    private $senderName;
    private $senderEmail;
    private $templatesDir;

    public function __construct() {
        $this->apiKey = BREVO_API_KEY;
        $this->senderName = BREVO_SENDER_NAME;
        $this->senderEmail = BREVO_SENDER_EMAIL;
        $this->templatesDir = EMAIL_TEMPLATES_DIR;
    }

    /**
     * Send a transactional email using Brevo API
     *
     * @param string $to Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlContent HTML content
     * @param array $params Optional template parameters
     * @return array Response with success status
     */
    public function send($to, $toName, $subject, $htmlContent, $params = []) {
        $url = 'https://api.brevo.com/v3/smtp/email';

        $data = [
            'sender' => [
                'name' => $this->senderName,
                'email' => $this->senderEmail
            ],
            'to' => [
                [
                    'email' => $to,
                    'name' => $toName
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent
        ];

        // Add template params if provided
        if (!empty($params)) {
            $data['params'] = $params;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'messageId' => $result['messageId'] ?? null];
        }

        return ['success' => false, 'error' => $result['message'] ?? 'Unknown error', 'code' => $httpCode];
    }

    /**
     * Send email using a Brevo template ID
     *
     * @param string $to Recipient email
     * @param string $toName Recipient name
     * @param int $templateId Brevo template ID
     * @param array $params Template parameters
     * @return array Response with success status
     */
    public function sendTemplate($to, $toName, $templateId, $params = []) {
        $url = 'https://api.brevo.com/v3/smtp/email';

        $data = [
            'to' => [
                [
                    'email' => $to,
                    'name' => $toName
                ]
            ],
            'templateId' => $templateId,
            'params' => $params
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => $error];
        }

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'messageId' => $result['messageId'] ?? null];
        }

        return ['success' => false, 'error' => $result['message'] ?? 'Unknown error', 'code' => $httpCode];
    }

    /**
     * Load and process a local email template
     *
     * @param string $templateName Template filename (without .php extension)
     * @param array $variables Variables to replace in template
     * @return string Processed HTML content
     */
    public function loadTemplate($templateName, $variables = []) {
        $templatePath = $this->templatesDir . '/' . $templateName . '.php';

        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: $templateName");
        }

        // Start output buffering
        ob_start();

        // Extract variables for use in template
        extract($variables);

        // Include template
        include $templatePath;

        // Get content and clean buffer
        $content = ob_get_clean();

        // Replace any remaining {{variable}} placeholders
        foreach ($variables as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $content = str_replace('{{' . $key . '}}', $value, $content);
            }
        }

        return $content;
    }

    /**
     * Send a templated email using local PHP template
     *
     * @param string $to Recipient email
     * @param string $toName Recipient name
     * @param string $templateName Template name
     * @param string $subject Email subject
     * @param array $variables Template variables
     * @return array Response with success status
     */
    public function sendLocalTemplate($to, $toName, $templateName, $subject, $variables = []) {
        try {
            $htmlContent = $this->loadTemplate($templateName, $variables);
            return $this->send($to, $toName, $subject, $htmlContent);
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ===========================================
    // CONVENIENCE METHODS FOR COMMON EMAILS
    // ===========================================

    /**
     * Send welcome email to new customer
     */
    public function sendCustomerWelcome($email, $name, $firstName, $referralCode = null) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'client-welcome',
            'Bine ai venit pe ' . SITE_NAME . '!',
            [
                'prenume' => $firstName,
                'name' => $name,
                'referral_code' => $referralCode ?? '',
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ]
        );
    }

    /**
     * Send email confirmation to customer
     */
    public function sendCustomerEmailConfirmation($email, $name, $firstName, $confirmationLink) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'client-email-confirmation',
            'Confirma adresa de email - ' . SITE_NAME,
            [
                'prenume' => $firstName,
                'name' => $name,
                'confirmation_link' => $confirmationLink,
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ]
        );
    }

    /**
     * Send order confirmation to customer
     */
    public function sendOrderConfirmation($email, $name, $firstName, $orderData) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'client-order-confirmation',
            'Confirmare comanda #' . $orderData['order_id'] . ' - ' . SITE_NAME,
            array_merge($orderData, [
                'prenume' => $firstName,
                'name' => $name,
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ])
        );
    }

    /**
     * Send welcome email to new organizer
     */
    public function sendOrganizerWelcome($email, $name, $contactName) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'organizer-welcome',
            'Bine ai venit in comunitatea de organizatori ' . SITE_NAME . '!',
            [
                'contact_name' => $contactName,
                'company_name' => $name,
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ]
        );
    }

    /**
     * Send payment confirmation to organizer
     */
    public function sendOrganizerPaymentConfirmation($email, $name, $paymentData) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'organizer-payment-confirmation',
            'Plata procesata - ' . SITE_NAME,
            array_merge($paymentData, [
                'company_name' => $name,
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ])
        );
    }

    /**
     * Send ticket to beneficiary
     */
    public function sendTicketToBeneficiary($email, $name, $ticketData) {
        return $this->sendLocalTemplate(
            $email,
            $name,
            'ticket-beneficiary',
            'Biletul tau pentru ' . $ticketData['event_name'] . ' - ' . SITE_NAME,
            array_merge($ticketData, [
                'beneficiary_name' => $name,
                'site_name' => SITE_NAME,
                'site_url' => SITE_URL,
                'support_email' => SUPPORT_EMAIL
            ])
        );
    }
}

// Global email service instance
$emailService = new AmbiletEmailService();

/**
 * Helper function to get email service
 */
function getEmailService() {
    global $emailService;
    return $emailService;
}
