<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class AllEmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Registration & Welcome
            [
                'name' => 'Registration Confirmation',
                'event_trigger' => 'registration_confirmation',
                'subject' => 'Verify Your Email - {{public_name}}',
                'body' => '<h2>Welcome to {{public_name}}, {{first_name}}!</h2>
<p>Thank you for registering. Please verify your email address by clicking the button below:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{verification_link}}" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Verify Email Address</a>
</p>
<p>If you did not create an account, no further action is required.</p>
<p>This verification link will expire in 24 hours.</p>',
                'description' => 'Sent when a new user registers to verify their email address',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'verification_link'],
                'is_active' => true,
            ],
            [
                'name' => 'Welcome Email',
                'event_trigger' => 'welcome_email',
                'subject' => 'Welcome to {{public_name}} - Let\'s Get Started!',
                'body' => '<h2>Welcome Aboard, {{first_name}}!</h2>
<p>Your email has been verified and your account is now active.</p>
<p>Here\'s what you can do next:</p>
<ul>
    <li>Complete your profile settings</li>
    <li>Explore available features</li>
    <li>Set up your first event or venue</li>
</ul>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{website_url}}/admin" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Go to Dashboard</a>
</p>
<p>If you need any help, our support team is here for you.</p>',
                'description' => 'Sent after email verification is complete',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'website_url', 'company_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Password Reset',
                'event_trigger' => 'password_reset',
                'subject' => 'Reset Your Password - {{public_name}}',
                'body' => '<h2>Password Reset Request</h2>
<p>Hi {{first_name}},</p>
<p>We received a request to reset your password. Click the button below to create a new password:</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{reset_password_link}}" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Reset Password</a>
</p>
<p>This link will expire in 60 minutes.</p>
<p>If you didn\'t request this password reset, please ignore this email or contact support if you have concerns.</p>',
                'description' => 'Sent when user requests password reset',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'reset_password_link'],
                'is_active' => true,
            ],

            // Invoice & Payment
            [
                'name' => 'Invoice Notification',
                'event_trigger' => 'invoice_notification',
                'subject' => 'New Invoice #{{invoice_number}} from {{public_name}}',
                'body' => '<h2>New Invoice</h2>
<p>Hi {{first_name}},</p>
<p>A new invoice has been generated for your account:</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Invoice Number:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{invoice_number}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Amount:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{invoice_amount}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Due Date:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{due_date}}</td>
    </tr>
</table>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{invoice_url}}" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">View Invoice</a>
</p>',
                'description' => 'Sent when a new invoice is generated',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'invoice_number', 'invoice_amount', 'due_date', 'invoice_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Payment Received',
                'event_trigger' => 'payment_received',
                'subject' => 'Payment Received - Thank You!',
                'body' => '<h2>Payment Confirmed</h2>
<p>Hi {{first_name}},</p>
<p>We\'ve received your payment. Thank you!</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Invoice Number:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{invoice_number}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Amount Paid:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{payment_amount}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Payment Date:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{payment_date}}</td>
    </tr>
</table>
<p>A receipt has been sent to your email for your records.</p>',
                'description' => 'Sent when payment is successfully processed',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'invoice_number', 'payment_amount', 'payment_date'],
                'is_active' => true,
            ],
            [
                'name' => 'Payment Failed',
                'event_trigger' => 'payment_failed',
                'subject' => 'Payment Failed - Action Required',
                'body' => '<h2>Payment Failed</h2>
<p>Hi {{first_name}},</p>
<p>We were unable to process your payment for invoice #{{invoice_number}}.</p>
<p><strong>Reason:</strong> {{failure_reason}}</p>
<p>Please update your payment method and try again to avoid service interruption.</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{payment_url}}" style="background-color: #ef4444; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Update Payment Method</a>
</p>
<p>If you need assistance, please contact our support team.</p>',
                'description' => 'Sent when a payment fails to process',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'invoice_number', 'failure_reason', 'payment_url'],
                'is_active' => true,
            ],

            // Domain Management
            [
                'name' => 'Domain Activated',
                'event_trigger' => 'domain_activated',
                'subject' => 'Your Domain {{domain_name}} is Now Active!',
                'body' => '<h2>Domain Activated</h2>
<p>Hi {{first_name}},</p>
<p>Great news! Your domain <strong>{{domain_name}}</strong> has been successfully activated and is now live.</p>
<p>You can now:</p>
<ul>
    <li>Access your website at <a href="https://{{domain_name}}">{{domain_name}}</a></li>
    <li>Configure custom settings for this domain</li>
    <li>Set up SSL certificate (if not already done)</li>
</ul>
<p style="text-align: center; margin: 30px 0;">
    <a href="https://{{domain_name}}" style="background-color: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Visit Your Site</a>
</p>',
                'description' => 'Sent when a domain is activated for a tenant',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'domain_name'],
                'is_active' => true,
            ],
            [
                'name' => 'Domain Suspended',
                'event_trigger' => 'domain_suspended',
                'subject' => 'Domain {{domain_name}} Has Been Suspended',
                'body' => '<h2>Domain Suspended</h2>
<p>Hi {{first_name}},</p>
<p>Your domain <strong>{{domain_name}}</strong> has been suspended.</p>
<p><strong>Reason:</strong> {{suspension_reason}}</p>
<p>To reactivate your domain, please:</p>
<ol>
    <li>Review the suspension reason</li>
    <li>Resolve any outstanding issues</li>
    <li>Contact support if you need assistance</li>
</ol>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{website_url}}/admin" style="background-color: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Go to Dashboard</a>
</p>',
                'description' => 'Sent when a domain is suspended',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'domain_name', 'suspension_reason', 'website_url'],
                'is_active' => true,
            ],

            // Microservices
            [
                'name' => 'Microservice Activated',
                'event_trigger' => 'microservice_activated',
                'subject' => '{{microservice_name}} is Now Active on Your Account',
                'body' => '<h2>Microservice Activated</h2>
<p>Hi {{first_name}},</p>
<p>The <strong>{{microservice_name}}</strong> microservice has been activated on your account.</p>
<p>You now have access to:</p>
<ul>
    <li>All {{microservice_name}} features</li>
    <li>Dedicated documentation and support</li>
    <li>Configuration options in your dashboard</li>
</ul>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{website_url}}/admin" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Configure Now</a>
</p>
<p>Check out our documentation to get started with {{microservice_name}}.</p>',
                'description' => 'Sent when a new microservice is enabled for a tenant',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'microservice_name', 'website_url'],
                'is_active' => true,
            ],

            // Subscription Management
            [
                'name' => 'Trial Ending',
                'event_trigger' => 'trial_ending',
                'subject' => 'Your Trial Ends in {{days_remaining}} Days',
                'body' => '<h2>Trial Ending Soon</h2>
<p>Hi {{first_name}},</p>
<p>Your free trial of {{plan}} will end in <strong>{{days_remaining}} days</strong>.</p>
<p>To continue enjoying all features without interruption, please upgrade your subscription.</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{upgrade_url}}" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Upgrade Now</a>
</p>
<p>If you have any questions about our plans, feel free to reach out to our team.</p>',
                'description' => 'Sent when trial period is about to expire',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'plan', 'days_remaining', 'upgrade_url'],
                'is_active' => true,
            ],
            [
                'name' => 'Subscription Renewed',
                'event_trigger' => 'subscription_renewed',
                'subject' => 'Subscription Renewed Successfully',
                'body' => '<h2>Subscription Renewed</h2>
<p>Hi {{first_name}},</p>
<p>Your subscription to <strong>{{plan}}</strong> has been renewed successfully.</p>
<table style="width: 100%; margin: 20px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Plan:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{plan}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Next Billing Date:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{next_billing_date}}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;"><strong>Amount:</strong></td>
        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">{{renewal_amount}}</td>
    </tr>
</table>
<p>Thank you for your continued trust in {{public_name}}!</p>',
                'description' => 'Sent when subscription is renewed',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'plan', 'next_billing_date', 'renewal_amount'],
                'is_active' => true,
            ],
            [
                'name' => 'Subscription Cancelled',
                'event_trigger' => 'subscription_cancelled',
                'subject' => 'Subscription Cancellation Confirmed',
                'body' => '<h2>Subscription Cancelled</h2>
<p>Hi {{first_name}},</p>
<p>Your subscription to <strong>{{plan}}</strong> has been cancelled as requested.</p>
<p>Your access will continue until <strong>{{access_end_date}}</strong>.</p>
<p>We\'re sorry to see you go! If you change your mind, you can resubscribe at any time.</p>
<p style="text-align: center; margin: 30px 0;">
    <a href="{{resubscribe_url}}" style="background-color: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">Resubscribe</a>
</p>
<p>If you have feedback on how we can improve, we\'d love to hear from you.</p>',
                'description' => 'Sent when subscription is cancelled',
                'available_variables' => ['first_name', 'last_name', 'email', 'public_name', 'plan', 'access_end_date', 'resubscribe_url'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['event_trigger' => $template['event_trigger']],
                $template
            );
        }

        $this->command->info('Created/updated ' . count($templates) . ' email templates.');
    }
}
